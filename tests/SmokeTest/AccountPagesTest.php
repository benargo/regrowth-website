<?php

namespace Tests\SmokeTest;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_index_loads(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_account_index_redirects_unauthenticated_users(): void
    {
        $response = $this->get(route('account.index'));

        $response->assertRedirect('/login');
    }
}
