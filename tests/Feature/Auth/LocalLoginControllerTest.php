<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class LocalLoginControllerTest extends TestCase
{
    use RefreshDatabase;

    // ==================== create ====================

    #[Test]
    #[Group('happy-path')]
    public function create_renders_the_local_login_form(): void
    {
        $response = $this->get(route('login.local'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Auth/LocalLogin'));
    }

    #[Test]
    #[Group('authorization')]
    public function create_returns_404_when_the_environment_is_not_local_or_testing(): void
    {
        App::detectEnvironment(fn () => 'production');

        $response = $this->get(route('login.local'));

        $response->assertNotFound();
    }

    #[Test]
    #[Group('authorization')]
    public function create_redirects_an_authenticated_user_away_from_the_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('login.local'));

        $response->assertRedirect('/');
    }

    // ==================== store ====================

    #[Test]
    #[Group('happy-path')]
    public function store_logs_in_the_user_with_a_valid_id(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.local.store'), ['id' => $user->id]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/');
    }

    #[Test]
    #[Group('happy-path')]
    public function store_redirects_to_the_intended_url(): void
    {
        $user = User::factory()->create();

        $this->get(route('account.index'));

        $response = $this->post(route('login.local.store'), ['id' => $user->id]);

        $response->assertRedirect(route('account.index'));
    }

    #[Test]
    #[Group('validation')]
    public function store_rejects_a_missing_id(): void
    {
        $response = $this->from(route('login.local'))
            ->post(route('login.local.store'), []);

        $response->assertSessionHasErrors('id');
        $this->assertGuest();
    }

    #[Test]
    #[Group('validation')]
    public function store_rejects_an_id_that_does_not_exist(): void
    {
        $response = $this->from(route('login.local'))
            ->post(route('login.local.store'), ['id' => '999999999999999999']);

        $response->assertSessionHasErrors('id');
        $this->assertGuest();
    }

    #[Test]
    #[Group('validation')]
    public function store_rejects_a_non_string_id(): void
    {
        $response = $this->from(route('login.local'))
            ->post(route('login.local.store'), ['id' => ['an-array']]);

        $response->assertSessionHasErrors('id');
        $this->assertGuest();
    }

    #[Test]
    #[Group('authorization')]
    public function store_returns_404_when_the_environment_is_not_local_or_testing(): void
    {
        $user = User::factory()->create();

        App::detectEnvironment(fn () => 'production');

        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('login.local.store'), ['id' => $user->id]);

        $response->assertNotFound();
        $this->assertGuest();
    }
}
