<?php

namespace Tests\Feature\Database\Migrations;

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class AddGrantsPermissionsAndColorsToDiscordRolesTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function migration(): Migration
    {
        return require database_path('migrations/2026_11_02_135527_add_grants_permissions_and_colors_to_discord_roles_table.php');
    }

    protected function dropNewColumns(): void
    {
        Schema::table('discord_roles', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'secondary_color', 'tertiary_color', 'grants_permissions']);
        });
    }

    // ==================== up ====================

    #[Test]
    public function up_grants_permissions_to_roles_that_have_a_permission(): void
    {
        $this->dropNewColumns();

        DB::table('discord_roles')->insert([
            ['id' => '1', 'name' => 'With', 'position' => 1, 'is_visible' => true],
            ['id' => '2', 'name' => 'Without', 'position' => 2, 'is_visible' => true],
        ]);
        $permission = Permission::factory()->create();
        DB::table('discord_role_has_permissions')->insert([
            'discord_role_id' => '1',
            'permission_id' => $permission->id,
        ]);

        $this->migration()->up();

        $this->assertEquals(1, DB::table('discord_roles')->where('id', '1')->value('grants_permissions'));
        $this->assertEquals(0, DB::table('discord_roles')->where('id', '2')->value('grants_permissions'));
    }
}
