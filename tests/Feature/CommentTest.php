<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_list_comments_for_a_post(): void
    {
        $post = Post::factory()->create();
        Comment::factory()->count(2)->create(['post_id' => $post->id]);

        $this->getJson("/api/posts/{$post->id}/comments")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_only_authenticated_users_can_create_comments(): void
    {
        $post = Post::factory()->create();

        $response = $this->postJson("/api/posts/{$post->id}/comments", ['body' => 'Nice post!']);

        $this->assertTrue(in_array($response->getStatusCode(), [401, 403]));

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/posts/{$post->id}/comments", ['body' => 'Nice post!'])
            ->assertStatus(201);
    }
    public function test_comment_author_comes_strictly_from_token_never_the_body(): void
    {
        $post = Post::factory()->create();
        $authenticatedUser = User::factory()->create();
        $someOtherUser = User::factory()->create();

        Sanctum::actingAs($authenticatedUser);

        $response = $this->postJson("/api/posts/{$post->id}/comments", [
            'body' => 'Security test',
            'author_id' => $someOtherUser->id
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('comments', [
            'body' => 'Security test',
            'author_id' => $authenticatedUser->id,
        ]);
    }

    public function test_only_the_author_can_delete_their_comment(): void
    {
        $author = User::factory()->create();
        $intruder = User::factory()->create();
        $comment = Comment::factory()->create(['author_id' => $author->id]);
        Sanctum::actingAs($intruder);
        $this->deleteJson("/api/comments/{$comment->id}")
            ->assertStatus(403);

        Sanctum::actingAs($author);

        $response = $this->deleteJson("/api/comments/{$comment->id}");
        $this->assertTrue(in_array($response->getStatusCode(), [200, 204]));
    }
}
