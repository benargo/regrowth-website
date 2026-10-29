<?php

namespace Tests\Feature\Database\Migrations;

use App\Models\Character;
use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('platform')]
#[Group('raiding')]
class ConvertUuidColumnsToNativeUuidMigrationTest extends TestCase
{
    use DatabaseTruncation;

    /**
     * The UUID columns that a MySQL-created schema holds as char(36).
     *
     * @var array<string, array<int, string>>
     */
    private const LEGACY_COLUMNS = [
        'event_assignment_groups' => ['event_id'],
        'event_assignments' => ['event_id'],
        'events' => ['id'],
        'pivot_characters_raid_reports' => ['raid_report_id'],
        'pivot_events_bosses' => ['event_id'],
        'pivot_events_characters' => ['event_id'],
        'pivot_events_raids' => ['event_id'],
        'pivot_events_users' => ['event_id'],
        'pivot_report_links' => ['report_1', 'report_2'],
        'planned_absences' => ['id'],
        'reports' => ['id'],
    ];

    /**
     * @var array<int, string>
     */
    protected array $tablesToTruncate = ['pivot_events_characters', 'events', 'characters'];

    #[Test]
    public function up_converts_legacy_char_uuid_columns_to_native_uuid(): void
    {
        $this->downgradeToLegacyCharColumns();

        $this->migration()->up();

        foreach (self::LEGACY_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertSame('uuid', $this->columnType($table, $column), "{$table}.{$column}");
            }
        }
    }

    #[Test]
    public function up_restores_every_foreign_key_it_drops(): void
    {
        $this->downgradeToLegacyCharColumns();
        $before = $this->foreignKeys();

        $this->migration()->up();

        $this->assertEquals($before, $this->foreignKeys());
    }

    #[Test]
    public function up_keeps_existing_uuid_values_and_their_relations(): void
    {
        $event = Event::factory()->create();
        $character = Character::factory()->create();
        $event->characters()->attach($character);
        $this->downgradeToLegacyCharColumns();

        $this->migration()->up();

        $this->assertDatabaseHas('events', ['id' => $event->id]);
        $this->assertDatabaseHas('pivot_events_characters', ['event_id' => $event->id, 'character_id' => $character->id]);
    }

    #[Test]
    public function up_rejects_a_value_that_is_not_a_uuid_before_changing_the_schema(): void
    {
        $this->downgradeToLegacyCharColumns();
        Event::factory()->create();
        DB::table('events')->update(['id' => 'not-a-uuid']);
        $before = $this->foreignKeys();

        try {
            $this->migration()->up();
            $this->fail('Expected a RuntimeException for a value that is not a UUID.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('events.id', $exception->getMessage());
        }

        $this->assertSame('char', $this->columnType('events', 'id'));
        $this->assertEquals($before, $this->foreignKeys());
    }

    #[Test]
    public function up_leaves_a_native_uuid_schema_unchanged(): void
    {
        $before = $this->foreignKeys();

        $this->migration()->up();

        $this->assertSame('uuid', $this->columnType('events', 'id'));
        $this->assertEquals($before, $this->foreignKeys());
    }

    // ==================== helpers ====================

    /**
     * Truncate this test's rows, then return any legacy char(36) columns to
     * native uuid so later tests run against the migrated schema.
     */
    protected function tearDownDatabaseTruncation(): void
    {
        $this->truncateDatabaseTables();
        $this->migration()->up();
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_14_171500_convert_uuid_columns_to_native_uuid.php');
    }

    /**
     * Change every legacy UUID column to char(36), as a MySQL-created schema
     * holds it, dropping the foreign keys on those tables first and
     * recreating them afterwards.
     */
    private function downgradeToLegacyCharColumns(): void
    {
        $foreignKeys = $this->foreignKeys();

        foreach ($foreignKeys as $table => $keys) {
            foreach ($keys as $key) {
                DB::statement("alter table `{$table}` drop foreign key `{$key['name']}`");
            }
        }

        foreach (self::LEGACY_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("alter table `{$table}` modify `{$column}` char(36) character set utf8mb4 collate utf8mb4_unicode_ci not null");
            }
        }

        foreach ($foreignKeys as $table => $keys) {
            foreach ($keys as $key) {
                $columns = implode('`, `', $key['columns']);
                $foreignColumns = implode('`, `', $key['foreign_columns']);

                DB::statement("alter table `{$table}` add constraint `{$key['name']}` foreign key (`{$columns}`) references `{$key['foreign_table']}` (`{$foreignColumns}`) on delete {$key['on_delete']} on update {$key['on_update']}");
            }
        }
    }

    private function columnType(string $table, string $column): string
    {
        return collect(Schema::getColumns($table))->firstWhere('name', $column)['type_name'];
    }

    /**
     * The foreign keys on every table that holds a legacy UUID column, keyed
     * by table.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function foreignKeys(): array
    {
        return collect(self::LEGACY_COLUMNS)
            ->keys()
            ->mapWithKeys(fn (string $table): array => [
                $table => collect(Schema::getForeignKeys($table))->sortBy('name')->values()->all(),
            ])
            ->all();
    }
}
