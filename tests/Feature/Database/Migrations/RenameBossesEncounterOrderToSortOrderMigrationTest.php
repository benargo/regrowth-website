<?php

namespace Tests\Feature\Database\Migrations;

use App\Models\Boss;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RenameBossesEncounterOrderToSortOrderMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function migration(): Migration
    {
        return require database_path('migrations/2026_08_28_140801_rename_bosses_encounter_order_to_sort_order.php');
    }

    #[Test]
    public function up_renames_the_column_and_preserves_values(): void
    {
        $boss = Boss::factory()->create(['sort_order' => 7]);

        $this->assertTrue(Schema::hasColumn('bosses', 'sort_order'));
        $this->assertFalse(Schema::hasColumn('bosses', 'encounter_order'));
        $this->assertSame(7, $boss->fresh()->sort_order);
    }

    #[Test]
    public function down_restores_the_encounter_order_column_and_up_reapplies(): void
    {
        $boss = Boss::factory()->create(['sort_order' => 4]);

        $this->migration()->down();

        $this->assertTrue(Schema::hasColumn('bosses', 'encounter_order'));
        $this->assertFalse(Schema::hasColumn('bosses', 'sort_order'));

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('bosses', 'sort_order'));
        $this->assertSame(4, $boss->fresh()->sort_order);
    }
}
