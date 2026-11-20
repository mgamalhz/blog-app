<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommentController extends Controller
{
    use AuthorizesRequests;

    public function index(Post $post): AnonymousResourceCollection
    {
        $comments = $post->comments()->with('author')->paginate(10);

        return CommentResource::collection($comments);
    }

    public function store(StoreCommentRequest $request, Post $post): CommentResource
    {
        $comment = $post->comments()->create([
            'body' => $request->input('body'),
            'author_id' => $request->user()->id,
        ]);

        return new CommentResource($comment->load('author'));
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);
        $comment->delete();

        return response()->json(null, 204);
    }
}
