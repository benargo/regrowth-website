<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('app:split-character-surnames {--revert : Join each surname back into the name}')]
#[Description('Split surname-version character names into name and surname, or join them back with --revert')]
class SplitCharacterSurnames extends Command
{
    /**
     * Split names on surname game versions into name and surname, or with --revert
     * join each surname back into the name. Applies every change in one transaction
     * and prints the changed rows.
     */
    public function handle(): int
    {
        $reverting = (bool) $this->option('revert');

        $changes = $reverting ? $this->joinChanges() : $this->splitChanges();

        if ($changes === []) {
            $this->components->info($reverting ? 'No character names to join.' : 'No character names to split.');

            return self::SUCCESS;
        }

        $this->applyChanges($changes, $reverting);

        $this->table(
            $reverting
                ? ['ID', 'Game version', 'Name', 'Surname', 'After']
                : ['ID', 'Game version', 'Before', 'Name', 'Surname'],
            $changes,
        );

        $count = count($changes);
        $this->components->info(($reverting ? 'Joined' : 'Split').' '.$count.' character '.Str::plural('name', $count).'.');

        return self::SUCCESS;
    }

    /**
     * Build the changes for every name on a surname game version that contains
     * whitespace: id, game version title, original name, new name and surname.
     *
     * @return list<array{0: int, 1: string|null, 2: string, 3: string, 4: string|null}>
     */
    private function splitChanges(): array
    {
        $rows = DB::table('characters')
            ->join('game_versions', 'game_versions.id', '=', 'characters.game_version_id')
            ->where('game_versions.uses_surnames', true)
            ->where('characters.name', 'like', '% %')
            ->select('characters.id', 'characters.name', 'game_versions.title')
            ->lazyById(500, 'characters.id', 'id');

        $changes = [];

        foreach ($rows as $row) {
            $parts = preg_split('/\s+/u', trim($row->name), 2);

            $changes[] = [$row->id, $row->title, $row->name, $parts[0], $parts[1] ?? null];
        }

        return $changes;
    }

    /**
     * Build the changes for every character with a surname: id, game version
     * title, name, surname and the joined name.
     *
     * @return list<array{0: int, 1: string|null, 2: string, 3: string, 4: string}>
     */
    private function joinChanges(): array
    {
        $rows = DB::table('characters')
            ->leftJoin('game_versions', 'game_versions.id', '=', 'characters.game_version_id')
            ->whereNotNull('characters.surname')
            ->select('characters.id', 'characters.name', 'characters.surname', 'game_versions.title')
            ->lazyById(500, 'characters.id', 'id');

        $changes = [];

        foreach ($rows as $row) {
            $changes[] = [$row->id, $row->title, $row->name, $row->surname, $row->name.' '.$row->surname];
        }

        return $changes;
    }

    /**
     * Write each change to the characters table inside one transaction, showing a
     * progress bar.
     *
     * @param  list<array<int, mixed>>  $changes
     */
    private function applyChanges(array $changes, bool $reverting): void
    {
        DB::transaction(function () use ($changes, $reverting): void {
            $this->withProgressBar($changes, function (array $change) use ($reverting): void {
                $values = $reverting
                    ? ['name' => $change[4], 'surname' => null]
                    : ['name' => $change[3], 'surname' => $change[4]];

                DB::table('characters')->where('id', $change[0])->update($values);
            });
        });

        $this->newLine();
    }
}
