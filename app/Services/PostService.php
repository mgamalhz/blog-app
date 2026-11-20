<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PostService
{
    public function create(array $data, User $author): Post
    {
        return DB::transaction(function () use ($data, $author) {
            $data['author_id'] = $author->id;
            $post = Post::create($data);

            if (! empty($data['tags'])) {
                $post->tags()->sync($data['tags']);
            }

            return $post->load(['author', 'category', 'tags']);
        });
    }

    public function update(Post $post, array $data): Post
    {
        return DB::transaction(function () use ($post, $data) {
            $post->update($data);

            if (isset($data['tags'])) {
                $post->tags()->sync($data['tags']);
            }

            return $post->load(['author', 'category', 'tags']);
        });
    }
}
