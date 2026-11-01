<?php

namespace Tests\Feature\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class MovePermissionsOntoPermissionGroupsMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function migration(): Migration
    {
        return require database_path('migrations/2026_11_01_130100_move_permissions_onto_permission_groups.php');
    }

    // ==================== down ====================

    #[Test]
    public function down_replaces_the_new_columns_with_group(): void
    {
        $this->migration()->down();

        $this->assertFalse(Schema::hasColumns('permissions', ['label', 'description', 'permission_group_id']));
        $this->assertTrue(Schema::hasColumn('permissions', 'group'));
    }

    #[Test]
    public function down_then_up_round_trips_each_permissions_group(): void
    {
        $this->migration()->down();
        DB::table('permissions')->insert([
            ['name' => 'view-attendance', 'guard_name' => 'web', 'group' => 'raid-management'],
            ['name' => 'view-warcraft-logs-guilds', 'guard_name' => 'web', 'group' => 'warcraftlogs'],
            ['name' => 'edit-datasets', 'guard_name' => 'web', 'group' => null],
        ]);

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(
            ['edit-datasets' => null, 'view-attendance' => 'raid-management', 'view-warcraft-logs-guilds' => 'warcraftlogs'],
            DB::table('permissions')->orderBy('name')->pluck('group', 'name')->all(),
        );
    }
}
