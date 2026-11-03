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

    public function test_it_returns_only_posts_matching_the_search_term(): void
    {
        Post::factory()->create(['title' => 'Learning Laravel API', 'published_at' => now()]);
        Post::factory()->create(['title' => 'Cooking Healthy Food', 'published_at' => now()]);

        $response = $this->getJson('/api/posts?search=Laravel');

        $response->assertStatus(200);
        $response->assertJsonFragment(['title' => 'Learning Laravel API']);
        $response->assertJsonMissing(['title' => 'Cooking Healthy Food']);
    }

    public function test_it_sorts_posts_by_title_in_ascending_order(): void
    {
        Post::factory()->create(['title' => 'Mohamed Post', 'published_at' => now()]);
        Post::factory()->create(['title' => 'Ahmed Post', 'published_at' => now()]);

        $response = $this->getJson('/api/posts?sort=title&direction=asc');

        $response->assertStatus(200);
        $this->assertEquals('Ahmed Post', $response->json('data.0.title'));
        $this->assertEquals('Mohamed Post', $response->json('data.1.title'));
    }

    public function test_it_returns_422_validation_error_for_invalid_sort_column(): void
    {
        $response = $this->getJson('/api/posts?sort=password');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sort']);
    }

    public function test_new_posts_are_created_as_drafts_by_default(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/posts', [
            'title' => 'My New Draft Post',
            'body' => 'Content here',
            'category_id' => $category->id,
            'published_at' => now()->toDateTimeString()
        ]);

        $response->assertStatus(201);
        $this->assertNull(Post::find($response->json('data.id'))->published_at);
    }

    public function test_guests_cannot_view_draft_posts(): void
    {
        $author = User::factory()->create();
        $draftPost = Post::factory()->create(['author_id' => $author->id]);

        $this->getJson("/api/posts/{$draftPost->id}")->assertStatus(404);

        $response = $this->getJson('/api/posts');
        $response->assertStatus(200)->assertJsonMissing(['id' => $draftPost->id]);
    }

    public function test_owner_can_publish_and_unpublish_their_post_correctly(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id]);

        Sanctum::actingAs($author);

        $this->postJson("/api/posts/{$post->id}/publish")->assertStatus(200);
        $this->assertNotNull($post->fresh()->published_at);

        $this->postJson("/api/posts/{$post->id}/unpublish")->assertStatus(200);
        $this->assertNull($post->fresh()->published_at);
    }

    public function test_non_owner_is_forbidden_from_changing_post_lifecycle(): void
    {
        $author = User::factory()->create();
        $intruder = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id]);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/posts/{$post->id}/publish")->assertStatus(403);
        $this->postJson("/api/posts/{$post->id}/unpublish")->assertStatus(403);
    }

    public function test_deleting_a_post_soft_deletes_it_and_returns_404_on_show(): void
    {
        $author = User::factory()->create();
        $category = Category::factory()->create();
        $post = Post::factory()->create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'published_at' => now()
        ]);

        Sanctum::actingAs($author);

        $this->deleteJson("/api/posts/{$post->id}")->assertStatus(204);

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
        $this->assertNotNull(Post::withTrashed()->find($post->id)->deleted_at);

        $this->getJson("/api/posts/{$post->id}")->assertStatus(404);
    }

    public function test_owner_can_restore_a_soft_deleted_post_successfully(): void
    {
        $author = User::factory()->create();
        $category = Category::factory()->create();
        $post = Post::factory()->create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'deleted_at' => now()
        ]);

        Sanctum::actingAs($author);

        $response = $this->postJson("/api/posts/{$post->id}/restore");
        $response->assertStatus(200);

        $this->assertNull($post->fresh()->deleted_at);
    }

    public function test_non_owner_is_forbidden_from_restoring_a_post(): void
    {
        $author = User::factory()->create();
        $intruder = User::factory()->create();
        $category = Category::factory()->create();
        $post = Post::factory()->create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'deleted_at' => now()
        ]);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/posts/{$post->id}/restore")->assertStatus(403);
    }

    public function test_trashed_endpoint_lists_only_the_current_users_deleted_posts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $category = Category::factory()->create();

        $myDeletedPost = Post::factory()->create(['author_id' => $userA->id, 'category_id' => $category->id, 'deleted_at' => now(), 'title' => 'My Trash']);
        $otherDeletedPost = Post::factory()->create(['author_id' => $userB->id, 'category_id' => $category->id, 'deleted_at' => now(), 'title' => 'Other Trash']);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/posts/trashed');

        $response->assertStatus(200);
        $response->assertJsonFragment(['title' => 'My Trash']);
        $response->assertJsonMissing(['title' => 'Other Trash']);
    }
}
