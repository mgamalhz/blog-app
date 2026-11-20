<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\SyncPostTagsRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\TagResource;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection(Tag::paginate(10));
    }

    public function store(StoreTagRequest $request): TagResource
    {
        $tag = Tag::create($request->validated());

        return new TagResource($tag);
    }

    public function syncTags(SyncPostTagsRequest $request, Post $post): PostResource
    {
        $post->tags()->sync($request->input('tags'));

        return new PostResource($post->load(['author', 'category', 'tags']));
    }
}
