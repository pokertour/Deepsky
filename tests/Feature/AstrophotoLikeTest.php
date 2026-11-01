<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AstrophotoLikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_like_a_photo(): void
    {
        $photo = Astrophoto::factory()->create();

        $response = $this->postJson(route('astrophoto.like', $photo));

        $response->assertOk()
            ->assertJson(['liked' => true, 'likes_count' => 1])
            ->assertCookie('liked_photos');

        $this->assertSame(1, $photo->refresh()->likes_count);
        $this->assertSame([$photo->id], json_decode($response->getCookie('liked_photos', false)->getValue(), true));
    }

    public function test_visitor_can_unlike_a_previously_liked_photo(): void
    {
        $photo = Astrophoto::factory()->create(['likes_count' => 3]);

        $response = $this->withCredentials()
            ->withUnencryptedCookie('liked_photos', json_encode([$photo->id]))
            ->postJson(route('astrophoto.like', $photo));

        $response->assertOk()->assertJson(['liked' => false, 'likes_count' => 2]);

        $this->assertSame(2, $photo->refresh()->likes_count);
        $this->assertSame([], json_decode($response->getCookie('liked_photos', false)->getValue(), true));
    }

    public function test_unlike_never_drives_counter_negative(): void
    {
        $photo = Astrophoto::factory()->create(['likes_count' => 0]);

        $this->withCredentials()
            ->withUnencryptedCookie('liked_photos', json_encode([$photo->id]))
            ->postJson(route('astrophoto.like', $photo))
            ->assertOk()
            ->assertJson(['liked' => false, 'likes_count' => 0]);
    }

    public function test_clearing_the_cookie_does_not_add_another_like(): void
    {
        $photo = Astrophoto::factory()->create();

        $this->postJson(route('astrophoto.like', $photo))->assertJson(['liked' => true, 'likes_count' => 1]);
        $this->postJson(route('astrophoto.like', $photo))->assertJson(['liked' => true, 'likes_count' => 1]);

        $this->assertSame(1, $photo->refresh()->likes_count);
        $this->assertDatabaseCount('astrophoto_likes', 1);
    }

    public function test_visitor_can_like_again_after_unliking(): void
    {
        $photo = Astrophoto::factory()->create();

        $this->postJson(route('astrophoto.like', $photo))->assertJson(['likes_count' => 1]);
        $this->withCredentials()
            ->withUnencryptedCookie('liked_photos', json_encode([$photo->id]))
            ->postJson(route('astrophoto.like', $photo))
            ->assertJson(['liked' => false, 'likes_count' => 0]);
        $this->assertDatabaseCount('astrophoto_likes', 0);

        $this->withUnencryptedCookie('liked_photos', '[]')
            ->postJson(route('astrophoto.like', $photo))
            ->assertJson(['liked' => true, 'likes_count' => 1]);
    }

    public function test_different_visitors_each_count_once(): void
    {
        $photo = Astrophoto::factory()->create();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->postJson(route('astrophoto.like', $photo));
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])->postJson(route('astrophoto.like', $photo));

        $this->assertSame(2, $photo->refresh()->likes_count);
    }

    public function test_bots_cannot_like(): void
    {
        $photo = Astrophoto::factory()->create();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')
            ->postJson(route('astrophoto.like', $photo))
            ->assertForbidden();

        $this->assertSame(0, $photo->refresh()->likes_count);
    }

    public function test_like_endpoint_is_throttled(): void
    {
        $photo = Astrophoto::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            $this->postJson(route('astrophoto.like', $photo))->assertOk();
        }

        $this->postJson(route('astrophoto.like', $photo))->assertStatus(429);
    }

    public function test_welcome_page_can_sort_by_likes(): void
    {
        $leastLiked = Astrophoto::factory()->create(['likes_count' => 1, 'title' => 'Peu aimée']);
        $mostLiked = Astrophoto::factory()->create(['likes_count' => 9, 'title' => 'Star du vault']);

        $this->get(route('welcome', ['sort' => 'likes']))
            ->assertOk()
            ->assertSeeInOrder(['Star du vault', 'Peu aimée']);
    }

    public function test_welcome_page_can_sort_by_views(): void
    {
        Astrophoto::factory()->create(['views_count' => 2, 'title' => 'Discrète']);
        Astrophoto::factory()->create(['views_count' => 50, 'title' => 'Populaire']);

        $this->get(route('welcome', ['sort' => 'views']))
            ->assertOk()
            ->assertSeeInOrder(['Populaire', 'Discrète']);
    }
}
