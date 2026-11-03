<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Post;


use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Requests\IndexPostRequest;

use App\Http\Resources\PostResource;
use App\Services\PostService;

use Illuminate\Support\Carbon;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    protected PostService $postService;
    public function __construct(PostService $postService)
    {
        $this->postService = $postService;
    }

    public function index(IndexPostRequest $request): AnonymousResourceCollection
    {

        $validated = $request->validated();
        $sortColumn = $validated['sort'] ?? 'created_at';
        $sortDirection = $validated['direction'] ?? 'desc';

        $posts = Post::query()
            ->with(['category', 'author'])
            ->withCount('comments')
            ->when($validated['category_id'] ?? null, function ($query, $categoryId) {
                return $query->where('category_id', $categoryId);
            })
            ->when($validated['author_id'] ?? null, function ($query, $authorId) {
                return $query->where('author_id', $authorId);
            })
            ->when($validated['search'] ?? null, function ($query, $searchTerm) {
                $query->where(function ($subQuery) use ($searchTerm) {
                    $subQuery->where('title', 'like', '%' . $searchTerm . '%')
                        ->orWhere('body', 'like', '%' . $searchTerm . '%');
                });
            })
            ->where(function ($query) {
                $query->published()
                    ->orWhere('author_id', Auth::id());
            })
            ->orderBy($sortColumn, $sortDirection)
            ->paginate(15)
            ->withQueryString();

        return PostResource::collection($posts);
    }

    public function show(Post $post): PostResource
    {
        if (is_null($post->published_at) && $post->author_id !== Auth::id()) {
            abort(404);
        }
        $post->load(['category', 'author']);
        return new PostResource($post);
    }

    public function store(StorePostRequest $request): PostResource
    {
        $validated = $request->validated();
        if ($request->has('tags')) {
            $validated['tags'] = $request->input('tags');
        }
        $post = $this->postService->create($validated, $request->user());
        return new PostResource($post);
    }

    public function update(UpdatePostRequest $request, Post $post): PostResource
    {
        $this->authorize('update', $post);
        $validated = $request->validated();
        if ($request->has('tags')) {
            $validated['tags'] = $request->input('tags');
        }

        $updatedPost = $this->postService->update($post, $validated);
        return new PostResource($post);
    }

    public function publish(Post $post)
    {
        $this->authorize('publish', $post);
        if (is_null($post->published_at)) {
            $post->published_at = Carbon::now();
            $post->save();
        }

        return response()->json(['message' => 'Post published successfully.']);
    }

    public function unpublish(Post $post)
    {
        $this->authorize('unpublish', $post);
        $post->published_at = null;
        $post->save();

        return response()->json(['message' => 'Post sent to draft.']);
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);
        $post->delete();
        return response()->json(null, 204);
    }

    public function trashed(): AnonymousResourceCollection
    {
        $posts = Post::onlyTrashed()
            ->where('author_id', Auth::id())
            ->with(['category', 'author'])
            ->paginate(15);

        return PostResource::collection($posts);
    }

    public function restore(Post $post)
    {
        $this->authorize('restore', $post);
        $post->restore();

        return response()->json(['message' => 'Post restored successfully.', 200]);
    }
}
