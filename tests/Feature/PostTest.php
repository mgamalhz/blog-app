<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Category;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_paginated_posts_with_correct_resource_shape(): void
    {
        Post::factory()->count(3)->create();

        $response = $this->getJson('/api/posts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta'
            ]);
    }

    public function test_authenticated_user_can_create_post_and_fails_on_validation(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson('/api/posts', [
            'title' => 'Valid Title',
            'body' => 'Valid Body',
            'category_id' => $category->id
        ])->assertStatus(201);

        $this->postJson('/api/posts', ['title' => '', 'body' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'body']);
    }

    public function test_guest_is_blocked_from_creating_a_post(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/posts', [
            'title' => 'Title by Guest',
            'body' => 'Body by Guest',
            'category_id' => $category->id
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [401, 403]));
    }

    public function test_post_update_and_delete_authorization_matrix(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $category = Category::factory()->create();
        $post = Post::factory()->create(['author_id' => $owner->id]);

        Sanctum::actingAs(new User(), []);
        $this->putJson("/api/posts/{$post->id}", ['title' => 'Updated'])->assertStatus(403);
        $this->deleteJson("/api/posts/{$post->id}")->assertStatus(403);

        Sanctum::actingAs($intruder);
        $this->putJson("/api/posts/{$post->id}", ['title' => 'Updated'])->assertStatus(403);
        $this->deleteJson("/api/posts/{$post->id}")->assertStatus(403);

        Sanctum::actingAs($owner);
        $this->putJson("/api/posts/{$post->id}", [
            'title' => 'New Title',
            'body' => 'New Body',
            'category_id' => $category->id
        ])->assertStatus(200);

        $this->deleteJson("/api/posts/{$post->id}")
            ->assertStatus(204);
    }
}
