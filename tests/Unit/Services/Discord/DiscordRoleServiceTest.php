<?php

namespace Tests\Unit\Services\Discord;

use App\Services\Discord\DiscordRoleService;
use App\Services\Discord\Exceptions\RoleNotFoundException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordRoleServiceTest extends TestCase
{
    private DiscordRoleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DiscordRoleService('test_bot_token', '829020506907869214');
    }

    #[Test]
    public function it_fetches_all_roles_successfully(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/roles' => Http::response([
                ['id' => '111', 'name' => 'Officer'],
                ['id' => '222', 'name' => 'Raider'],
            ], 200),
        ]);

        $roles = $this->service->getAllRoles();

        $this->assertCount(2, $roles);
        $this->assertSame('Officer', $roles[0]['name']);
        $this->assertSame('Raider', $roles[1]['name']);
    }

    #[Test]
    public function it_throws_runtime_exception_when_fetching_all_roles_fails(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/roles' => Http::response(
                'Internal Server Error',
                500,
            ),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch guild roles');

        $this->service->getAllRoles();
    }

    #[Test]
    public function it_gets_single_role_successfully(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/roles/111' => Http::response([
                'id' => '111',
                'name' => 'Officer',
                'color' => 15158332,
            ], 200),
        ]);

        $role = $this->service->getRole('111');

        $this->assertSame('111', $role['id']);
        $this->assertSame('Officer', $role['name']);
    }

    #[Test]
    public function it_throws_runtime_exception_when_getting_role_fails(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/roles/111' => Http::response(
                'Internal Server Error',
                500,
            ),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch guild roles');

        $this->service->getRole('111');
    }

    #[Test]
    public function it_throws_role_not_found_when_response_is_empty(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/roles/999' => Http::response([], 200),
        ]);

        $this->expectException(RoleNotFoundException::class);
        $this->expectExceptionMessage('Role 999 not found in guild 829020506907869214');

        $this->service->getRole('999');
    }

    #[Test]
    public function it_sends_correct_authorization_header(): void
    {
        Http::fake([
            'discord.com/api/v10/guilds/829020506907869214/roles' => Http::response([], 200),
        ]);

        $this->service->getAllRoles();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bot test_bot_token');
        });
    }
}
