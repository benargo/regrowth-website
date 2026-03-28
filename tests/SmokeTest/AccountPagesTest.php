<?php

namespace Tests\SmokeTest;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountPagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function account_index_loads(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function account_index_redirects_unauthenticated_users(): void
    {
        $response = $this->get(route('account.index'));

        $response->assertRedirect('/login');
    }
}
