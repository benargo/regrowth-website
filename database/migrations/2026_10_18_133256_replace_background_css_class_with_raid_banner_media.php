<?php

use App\Models\Raid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The image in resources/images for each background CSS class.
     *
     * @var array<string, string>
     */
    private const BANNER_FILES = [
        'bg-raid-karazhan' => 'bg_karazhan_opera.webp',
        'bg-raid-gruul' => 'bg_gruul.webp',
        'bg-raid-magtheridon' => 'bg_magtheridon.webp',
        'bg-raid-serpentshrine-cavern' => 'bg_serpentshrinecavern.webp',
        'bg-raid-tempest-keep' => 'bg_tempestkeep.webp',
        'bg-raid-hyjal-summit' => 'bg_hyjalsummit.webp',
        'bg-raid-black-temple' => 'bg_blacktemple.webp',
        'bg-raid-zulaman' => 'bg_zulaman.webp',
        'bg-raid-sunwell-plateau' => 'bg_sunwell.webp',
        'bg-raid-gruul-magtheridon' => 'bg_gruul_magtheridon.webp',
        'bg-vashj-and-kaelthas' => 'bg_vashj_and_kaelthas.webp',
        'bg-ssctk' => 'bg_vashj_and_kaelthas.webp',
    ];

    /**
     * The CSS background position stored on the banner for each background
     * CSS class that is not centred.
     *
     * @var array<string, string>
     */
    private const BANNER_POSITIONS = [
        'bg-raid-serpentshrine-cavern' => 'center bottom',
        'bg-raid-tempest-keep' => 'center bottom',
    ];

    /**
     * Attach each raid's background image to its banner collection, then drop
     * background_css_class from raids and events. Raids that already have a
     * banner, and rows with an unmapped class or a missing file, are skipped
     * with a warning. Each table is left untouched when it no longer has the
     * column.
     */
    public function up(): void
    {
        if (Schema::hasColumn('raids', 'background_css_class')) {
            $this->attachBanners();

            Schema::table('raids', function (Blueprint $table): void {
                $table->dropColumn('background_css_class');
            });
        }

        if (Schema::hasColumn('events', 'background_css_class')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropColumn('background_css_class');
            });
        }
    }

    /**
     * Attach the background image mapped from each raid's background CSS
     * class to its banner collection.
     */
    private function attachBanners(): void
    {
        $rows = DB::table('raids')
            ->whereNotNull('background_css_class')
            ->get(['id', 'background_css_class']);

        foreach ($rows as $row) {
            $file = self::BANNER_FILES[$row->background_css_class] ?? null;
            $path = $file === null ? null : resource_path("images/{$file}");

            if ($path === null || ! file_exists($path)) {
                echo "Skipped raid {$row->id}: no banner image for '{$row->background_css_class}'.".PHP_EOL;

                continue;
            }

            $raid = Raid::findOrFail($row->id);

            if ($raid->hasMedia(Raid::MEDIA_BANNER)) {
                echo "Skipped raid {$row->id}: it already has a banner.".PHP_EOL;

                continue;
            }

            $position = self::BANNER_POSITIONS[$row->background_css_class] ?? null;

            $raid->addMedia($path)
                ->preservingOriginal()
                ->withCustomProperties($position === null ? [] : ['position' => $position])
                ->toMediaCollection(Raid::MEDIA_BANNER);
        }
    }
};
