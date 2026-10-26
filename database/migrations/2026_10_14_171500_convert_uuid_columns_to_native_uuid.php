<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The UUID primary keys to convert, keyed by table.
     *
     * @var array<string, string>
     */
    private const UUID_PRIMARY_KEYS = [
        'events' => 'id',
        'planned_absences' => 'id',
        'reports' => 'id',
    ];

    /**
     * The pattern every value must match before its column is converted.
     */
    private const UUID_PATTERN = '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$';

    /**
     * Convert the UUID primary keys, and every column that references them
     * through a foreign key, from char(36) to the native uuid type. Each
     * value is checked to be a UUID before the schema changes, and a column
     * that is already uuid is left untouched. The foreign keys on those
     * columns are dropped for the conversion and recreated as they were.
     */
    public function up(): void
    {
        $foreignKeys = $this->foreignKeys();
        $uuidColumns = $this->uuidColumns($foreignKeys);

        $legacyColumns = $uuidColumns->reject(
            fn (array $column): bool => $this->columnType($column['table'], $column['column']) === 'uuid'
        );

        if ($legacyColumns->isEmpty()) {
            return;
        }

        $legacyColumns->each(fn (array $column) => $this->ensureValuesAreUuids($column['table'], $column['column']));

        $affectedForeignKeys = $foreignKeys->filter(
            fn (array $foreignKey): bool => $this->touchesColumns($foreignKey, $uuidColumns)
        );

        $affectedForeignKeys->each(function (array $foreignKey): void {
            Schema::table($foreignKey['table'], function (Blueprint $table) use ($foreignKey): void {
                $table->dropForeign($foreignKey['name']);
            });
        });

        $legacyColumns->each(function (array $column): void {
            $nullable = collect(Schema::getColumns($column['table']))->firstWhere('name', $column['column'])['nullable'];

            Schema::table($column['table'], function (Blueprint $table) use ($column, $nullable): void {
                $table->uuid($column['column'])->nullable($nullable)->change();
            });
        });

        $affectedForeignKeys->each(function (array $foreignKey): void {
            Schema::table($foreignKey['table'], function (Blueprint $table) use ($foreignKey): void {
                $table->foreign($foreignKey['columns'], $foreignKey['name'])
                    ->references($foreignKey['foreign_columns'])
                    ->on($foreignKey['foreign_table'])
                    ->onDelete($foreignKey['on_delete'])
                    ->onUpdate($foreignKey['on_update']);
            });
        });
    }

    /**
     * Every foreign key in the current schema, with the table it belongs to.
     *
     * @return Collection<int, array{table: string, name: string, columns: array<int, string>, foreign_table: string, foreign_columns: array<int, string>, on_update: string, on_delete: string}>
     */
    private function foreignKeys(): Collection
    {
        return collect(Schema::getTableListing(Schema::getCurrentSchemaName(), false))
            ->flatMap(fn (string $table): array => array_map(
                fn (array $foreignKey): array => ['table' => $table, ...$foreignKey],
                Schema::getForeignKeys($table),
            ))
            ->values();
    }

    /**
     * The UUID primary keys plus every column that references one of them,
     * directly or through another referencing column.
     *
     * @param  Collection<int, array<string, mixed>>  $foreignKeys
     * @return Collection<int, array{table: string, column: string}>
     */
    private function uuidColumns(Collection $foreignKeys): Collection
    {
        $columns = collect(self::UUID_PRIMARY_KEYS)
            ->map(fn (string $column, string $table): array => ['table' => $table, 'column' => $column])
            ->values();

        do {
            $count = $columns->count();

            foreach ($foreignKeys as $foreignKey) {
                foreach ($foreignKey['foreign_columns'] as $index => $foreignColumn) {
                    $referenced = ['table' => $foreignKey['foreign_table'], 'column' => $foreignColumn];
                    $referencing = ['table' => $foreignKey['table'], 'column' => $foreignKey['columns'][$index]];

                    if ($columns->contains($referenced) && ! $columns->contains($referencing)) {
                        $columns->push($referencing);
                    }
                }
            }
        } while ($columns->count() > $count);

        return $columns;
    }

    /**
     * Determine whether the foreign key includes any of the given columns,
     * on either its own table or the table it references.
     *
     * @param  array<string, mixed>  $foreignKey
     * @param  Collection<int, array{table: string, column: string}>  $columns
     */
    private function touchesColumns(array $foreignKey, Collection $columns): bool
    {
        foreach ($foreignKey['columns'] as $index => $column) {
            if ($columns->contains(['table' => $foreignKey['table'], 'column' => $column])
                || $columns->contains(['table' => $foreignKey['foreign_table'], 'column' => $foreignKey['foreign_columns'][$index]])) {
                return true;
            }
        }

        return false;
    }

    private function columnType(string $table, string $column): string
    {
        return collect(Schema::getColumns($table))->firstWhere('name', $column)['type_name'];
    }

    /**
     * Throw when the column holds a value that is not a UUID.
     *
     * @throws RuntimeException
     */
    private function ensureValuesAreUuids(string $table, string $column): void
    {
        $invalid = DB::table($table)
            ->whereNotNull($column)
            ->whereRaw("`{$column}` not regexp ?", [self::UUID_PATTERN])
            ->count();

        if ($invalid > 0) {
            throw new RuntimeException("{$table}.{$column} holds {$invalid} value(s) that are not UUIDs.");
        }
    }
};
