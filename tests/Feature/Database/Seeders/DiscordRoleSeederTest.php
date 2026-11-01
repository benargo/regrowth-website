<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\DiscordRole;
use Database\Seeders\DiscordRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class DiscordRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICER_ID = '829021769448816691';

    private const MEMBER_ID = '829022020301094922';

    #[Test]
    public function it_seeds_the_four_roles_without_member(): void
    {
        $this->seed(DiscordRoleSeeder::class);

        $this->assertSame(4, DiscordRole::count());
        $this->assertNull(DiscordRole::find(self::MEMBER_ID));
        $this->assertTrue(DiscordRole::find(self::OFFICER_ID)->grants_permissions);
        $this->assertFalse(DiscordRole::find('829022292590985226')->grants_permissions);
        $this->assertSame('aa3b3b', DiscordRole::find(self::OFFICER_ID)->primary_color);
    }

    #[Test]
    public function it_does_not_overwrite_existing_roles(): void
    {
        DiscordRole::factory()->create([
            'id' => self::OFFICER_ID,
            'name' => 'Officer',
            'position' => 42,
            'grants_permissions' => false,
            'is_visible' => false,
            'primary_color' => '000001',
        ]);

        $this->seed(DiscordRoleSeeder::class);

        $officer = DiscordRole::find(self::OFFICER_ID);
        $this->assertSame(42, $officer->position);
        $this->assertFalse($officer->grants_permissions);
        $this->assertFalse($officer->is_visible);
        $this->assertSame('000001', $officer->primary_color);
    }
}
