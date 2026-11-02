<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\DiscordRoleResource;
use App\Models\DiscordRole;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class DiscordRoleResourceTest extends TestCase
{
    #[Test]
    #[Group('resource')]
    public function it_limits_the_badge_scope_to_the_name_and_primary_color(): void
    {
        $role = DiscordRole::factory()->make(['name' => 'Officer', 'primary_color' => 'aa3b3b']);

        $this->assertSame(
            ['name' => 'Officer', 'primary_color' => 'aa3b3b'],
            (new DiscordRoleResource($role))->scope('badge')->resolve(Request::create('/')),
        );
    }
}
