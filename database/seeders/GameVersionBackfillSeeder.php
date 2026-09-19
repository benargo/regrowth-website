<?php

namespace Database\Seeders;

use App\Models\Boss;
use App\Models\GameVersion;
use App\Models\GuildTag;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Raid;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * On-demand, re-runnable seeder that assigns a chosen GameVersion to every
 * dataset row currently missing one, and attaches every existing
 * PlayableRace/PlayableClass to that version. Not called from
 * DatabaseSeeder::run() — invoke explicitly, e.g. from tinker:
 *
 *   $seeder = new \Database\Seeders\GameVersionBackfillSeeder;
 *   $seeder->gameVersionId = 1;
 *   $seeder->run();
 */
class GameVersionBackfillSeeder extends Seeder
{
    public ?int $gameVersionId = null;

    /**
     * @return array<int, class-string<Model>>
     */
    protected function backfillModels(): array
    {
        return [Boss::class, Phase::class, Raid::class, GuildTag::class, Zone::class];
    }

    public function run(): void
    {
        $gameVersion = $this->resolveGameVersion();

        foreach ($this->backfillModels() as $model) {
            $updated = $model::query()
                ->whereNull('game_version_id')
                ->update(['game_version_id' => $gameVersion->id]);

            $this->command?->line("{$model}: {$updated} rows backfilled.");
        }

        PlayableRace::each(fn (PlayableRace $race) => $race->gameVersions()->syncWithoutDetaching([$gameVersion->id]));
        PlayableClass::each(fn (PlayableClass $class) => $class->gameVersions()->syncWithoutDetaching([$gameVersion->id]));
    }

    private function resolveGameVersion(): GameVersion
    {
        if ($this->gameVersionId !== null) {
            return GameVersion::findOrFail($this->gameVersionId);
        }

        $count = GameVersion::count();

        if ($count === 0) {
            throw new RuntimeException('No GameVersion rows exist to backfill onto. Seed one first.');
        }

        if ($count > 1) {
            throw new RuntimeException('Multiple GameVersion rows exist; set gameVersionId before calling run().');
        }

        return GameVersion::sole();
    }
}
