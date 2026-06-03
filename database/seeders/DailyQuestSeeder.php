<?php

namespace Database\Seeders;

use App\Contracts\HasBlizzardIcons;
use App\Enums\DailyQuestType;
use App\Enums\Instance;
use App\Enums\ItemQuality;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\Item\ItemData;
use App\Http\Integrations\Blizzard\Data\Media\MediaData;
use App\Http\Integrations\Blizzard\Exceptions\ItemNotFoundException;
use App\Http\Integrations\Blizzard\Exceptions\MediaNotFoundException;
use App\Http\Integrations\Blizzard\Exceptions\NotFoundException;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Jobs\AttachBlizzardIconToModel;
use App\Models\DailyQuest;
use App\Models\Item;
use App\Services\Blizzard\Exceptions\BlizzardRequestException;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\ForbiddenException;

class DailyQuestSeeder extends Seeder implements HasBlizzardIcons
{
    /** @var array<int, Lock> */
    private array $locks = [];

    public function __construct(
        private readonly BlizzardConnector $blizzard,
        private readonly RenderConnector $renderConnector,
    ) {}

    protected array $dailyQuests = [
        ['id' => 11380, 'name' => 'Manalicious', 'type' => DailyQuestType::Cooking, 'instance' => null],
        ['id' => 11377, 'name' => 'Revenge is Tasty', 'type' => DailyQuestType::Cooking, 'instance' => null],
        ['id' => 11381, 'name' => 'Soup for the Soul', 'type' => DailyQuestType::Cooking, 'instance' => null],
        ['id' => 11379, 'name' => 'Super Hot Stew', 'type' => DailyQuestType::Cooking, 'instance' => null],

        // Normal dungeon daily quests
        ['id' => 11371, 'name' => 'Wanted: A Black Stalker Egg', 'type' => DailyQuestType::Dungeon, 'instance' => Instance::Underbog],
        ['id' => 11389, 'name' => 'Wanted: Arcatraz Sentinels', 'type' => DailyQuestType::Dungeon, 'instance' => Instance::Arcatraz],
        ['id' => 11390, 'name' => 'Wanted: Coilfang Myrmidons', 'type' => DailyQuestType::Dungeon, 'instance' => Instance::Steamvault],
        ['id' => 11376, 'name' => 'Wanted: Malicious Instructors', 'type' => DailyQuestType::Dungeon, 'instance' => Instance::ShadowLabyrinth],
        ['id' => 11383, 'name' => 'Wanted: Rift Lords', 'type' => DailyQuestType::Dungeon, 'instance' => Instance::BlackMorass],
        ['id' => 11364, 'name' => 'Wanted: Shattered Hand Centurions', 'type' => DailyQuestType::Dungeon, 'instance' => Instance::ShatteredHalls],
        // Magisters' Terrace is not yet implemented, so keep this quest commented out until it is
        // ['id' => 11500, 'name' => "Wanted: Sisters of Torment", 'type' => DailyQuestType::Dungeon, 'instance' => Instance::MagistersTerrace],
        ['id' => 11385, 'name' => 'Wanted: Sunseeker Channelers', 'type' => DailyQuestType::Dungeon, 'instance' => Instance::Botanica],
        ['id' => 11387, 'name' => 'Wanted: Tempest-Forge Destroyers', 'type' => DailyQuestType::Dungeon, 'instance' => Instance::Mechanar,         'mode' => 'Normal'],

        // Heroic dungeon daily quests
        ['id' => 11369, 'name' => 'Wanted: A Black Stalker Egg', 'type' => DailyQuestType::Heroic, 'instance' => Instance::Underbog],
        ['id' => 11384, 'name' => 'Wanted: A Warp Splinter Clipping', 'type' => DailyQuestType::Heroic, 'instance' => Instance::Botanica],
        ['id' => 11382, 'name' => "Wanted: Aeonus's Hourglass", 'type' => DailyQuestType::Heroic, 'instance' => Instance::BlackMorass],
        ['id' => 11363, 'name' => "Wanted: Bladefist's Seal", 'type' => DailyQuestType::Heroic, 'instance' => Instance::ShatteredHalls],
        ['id' => 11362, 'name' => "Wanted: Keli'dan's Feathered Stave", 'type' => DailyQuestType::Heroic, 'instance' => Instance::BloodFurnace],
        ['id' => 11375, 'name' => "Wanted: Murmur's Whisper", 'type' => DailyQuestType::Heroic, 'instance' => Instance::ShadowLabyrinth],
        ['id' => 11354, 'name' => "Wanted: Nazan's Riding Crop", 'type' => DailyQuestType::Heroic, 'instance' => Instance::HellfireRamparts],
        ['id' => 11386, 'name' => "Wanted: Pathaleon's Projector", 'type' => DailyQuestType::Heroic, 'instance' => Instance::Mechanar],
        ['id' => 11373, 'name' => "Wanted: Shaffar's Wondrous Pendant", 'type' => DailyQuestType::Heroic, 'instance' => Instance::ManaTombs],
        ['id' => 11378, 'name' => "Wanted: The Epoch Hunter's Head", 'type' => DailyQuestType::Heroic, 'instance' => Instance::OldHillsbradFoothills],
        ['id' => 11374, 'name' => "Wanted: The Exarch's Soul Gem", 'type' => DailyQuestType::Heroic, 'instance' => Instance::AuchenaiCrypts],
        ['id' => 11372, 'name' => 'Wanted: The Headfeathers of Ikiss', 'type' => DailyQuestType::Heroic, 'instance' => Instance::SethekkHalls],
        ['id' => 11368, 'name' => 'Wanted: The Heart of Quagmirran', 'type' => DailyQuestType::Heroic, 'instance' => Instance::SlavePens],
        ['id' => 11388, 'name' => 'Wanted: The Scroll of Skyriss', 'type' => DailyQuestType::Heroic, 'instance' => Instance::Arcatraz],
        // Magisters' Terrace is not yet implemented, so keep this quest commented out until it is
        // ['id' => 11499, 'name' => "Wanted: The Signet Ring of Prince Kael'thas", 'type' => DailyQuestType::Heroic, 'instance' => Instance::MagistersTerrace],
        ['id' => 11370, 'name' => "Wanted: The Warlord's Treatise", 'type' => DailyQuestType::Heroic, 'instance' => Instance::Steamvault],

        ['id' => 11666, 'name' => 'Bait Bandits', 'type' => DailyQuestType::Fishing, 'instance' => null],
        ['id' => 11665, 'name' => 'Crocolisks in the City', 'type' => DailyQuestType::Fishing, 'instance' => null],
        ['id' => 11669, 'name' => 'Felblood Fillet', 'type' => DailyQuestType::Fishing, 'instance' => null],
        ['id' => 11668, 'name' => "Shrimpin' Ain't Easy", 'type' => DailyQuestType::Fishing, 'instance' => null],
        ['id' => 11667, 'name' => 'The One That Got Away', 'type' => DailyQuestType::Fishing, 'instance' => null],

        ['id' => 11336, 'name' => 'Call to Arms: Alterac Valley', 'type' => DailyQuestType::PvP, 'instance' => Instance::AlteracValley],
        ['id' => 11335, 'name' => 'Call to Arms: Arathi Basin', 'type' => DailyQuestType::PvP, 'instance' => Instance::ArathiBasin],
        ['id' => 11337, 'name' => 'Call to Arms: Eye of the Storm', 'type' => DailyQuestType::PvP, 'instance' => Instance::EyeOfTheStorm],
        ['id' => 11338, 'name' => 'Call to Arms: Warsong Gulch', 'type' => DailyQuestType::PvP, 'instance' => Instance::WarsongGulch],
    ];

    /**
     * Seed all daily quests (Cooking, Dungeon, Fishing, PvP) and attach each
     * quest's category icon to its blizzard_icons media collection.
     */
    public function run(): void
    {
        $dailyQuests = $this->dailyQuests;

        foreach ($dailyQuests as $quest) {
            $model = DailyQuest::query()->updateOrCreate(
                ['id' => $quest['id']],
                ['name' => $quest['name'], 'type' => $quest['type'], 'instance' => $quest['instance']],
            );

            $this->syncRewards($model, $this->rewardsFor($quest));

            $this->command?->line("  <info>✓</info> [{$model->id}] {$model->name}");

            if ($model->hasMedia('blizzard_icons')) {
                continue;
            }

            $iconName = $model->type->icon();
            $iconFileName = $iconName.'.'.self::BLIZZARD_ICON_FILE_EXTENSION;

            try {
                $body = $this->renderConnector->send(new FetchAssetRequest($iconName))->body();

                $model->addMediaFromString($body)
                    ->usingFileName($iconFileName)
                    ->withCustomProperties(['size' => self::BLIZZARD_ICON_SIZE])
                    ->toMediaCollection('blizzard_icons');
            } catch (ForbiddenException $e) {
                AttachBlizzardIconToModel::dispatch(DailyQuest::class, $model->id, $this->typeIconUrl($iconFileName))
                    ->delay(now()->addMinutes(5));
                $this->command?->warn("  ⚠ [{$model->id}] Icon deferred (403) — retrying in 5 min");
            } catch (NotFoundException|FatalRequestException $e) {
                $this->command?->warn("  ⚠ [{$model->id}] Icon skipped — {$e->getMessage()}");

                continue;
            }
        }

        foreach ($this->locks as $lock) {
            $lock->release();
        }
    }

    /**
     * Ensure each rewarded item exists, then sync the quest's reward pivot with
     * the correct quantities. Reseeding overwrites quantities without duplicating rows.
     *
     * @param  array<int, array{item_id: int, quantity: int}>  $rewards
     */
    private function syncRewards(DailyQuest $quest, array $rewards): void
    {
        $syncData = [];

        foreach ($rewards as $reward) {
            $this->ensureItemExists($reward['item_id']);

            $syncData[$reward['item_id']] = ['quantity' => $reward['quantity'] ?? 1];
        }

        $quest->rewards()->sync($syncData);
    }

    /**
     * Ensure an Item row exists for the given Blizzard item ID, fetching its name
     * and icon from the Blizzard API on a cache miss. Mirrors ItemSeeder: dispatches
     * AttachBlizzardIconToModel when the icon fetch returns 403.
     */
    private function ensureItemExists(int $itemId): void
    {
        if (isset($this->locks[$itemId])) {
            return;
        }

        $lock = Cache::lock("daily-quest-seeder-item-{$itemId}", 60);

        if (! $lock->get()) {
            return;
        }

        $this->locks[$itemId] = $lock;

        if (Item::whereKey($itemId)->exists()) {
            return;
        }

        try {
            /** @var ItemData $itemDto */
            $itemDto = $this->blizzard->send(new GetItemRequest($itemId))->dto();

            /** @var MediaData $mediaDto */
            $mediaDto = $this->blizzard->send(new GetItemMediaRequest($itemId))->dto();
        } catch (ItemNotFoundException|BlizzardRequestException|FatalRequestException $e) {
            $this->command?->warn("  ⚠ [{$itemId}] Item skipped — {$e->getMessage()}");

            return;
        }

        $item = Item::withoutEvents(fn () => Item::updateOrCreate(
            ['id' => $itemId],
            [
                'name' => $itemDto->name,
                'quality' => ItemQuality::{$itemDto->quality->type},
            ],
        ));

        if ($item->hasMedia('blizzard_icons')) {
            return;
        }

        $asset = $mediaDto->assets[0] ?? null;

        if ($asset === null) {
            return;
        }

        try {
            $fileName = (string) Str::of($asset->value)->afterLast('/')->before('?');
            $body = $this->renderConnector->send(new FetchAssetRequest($asset->value))->body();

            $item->addMediaFromString($body)
                ->usingFileName($fileName)
                ->withCustomProperties(['size' => 56])
                ->toMediaCollection('blizzard_icons');
        } catch (ForbiddenException $e) {
            AttachBlizzardIconToModel::dispatch(Item::class, $item->id, $asset->value)
                ->delay(now()->addMinutes(5));
            $this->command?->warn("  ⚠ [{$itemId}] Icon deferred (403) — retrying in 5 min");
        } catch (MediaNotFoundException|RequestException $e) {
            $this->command?->warn("  ⚠ [{$itemId}] Icon skipped — {$e->getMessage()}");
        }
    }

    /**
     * Build the absolute render-CDN URL for a type icon, matching the URL that
     * FetchAssetRequest::boot() constructs from a bare filename. Used as the asset
     * URL when deferring the icon fetch to AttachBlizzardIconToModel after a 403.
     */
    private function typeIconUrl(string $iconName): string
    {
        return $this->renderConnector->resolveBaseUrl()
            ."/{$this->renderConnector->getRegion()->value}/icons/".self::BLIZZARD_ICON_SIZE."/{$iconName}";
    }

    /**
     * Resolve rewards for a quest data array. PvP quests delegate to the instance,
     * all others delegate to the type.
     *
     * @param  array{type: DailyQuestType, instance: Instance|null}  $quest
     * @return array<int, array{item_id: int, quantity: int}>
     */
    private function rewardsFor(array $quest): array
    {
        if ($quest['type'] === DailyQuestType::PvP) {
            return $quest['instance']->dailyQuestRewards();
        }

        return $quest['type']->rewards();
    }
}
