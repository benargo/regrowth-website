<?php

namespace App\Actions\Boss;

use App\Actions\Datasets\BuildDatasetRoutes;
use App\Enums\RaidSetupStep;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/**
 * Resolves the URLs the boss management pages link to. A form opened from a
 * raid's Bosses step or Edit page leads back there.
 */
class BuildBossRoutes extends BuildDatasetRoutes
{
    /**
     * The `from` value of a Create form opened from a raid's Edit page.
     */
    public const string FROM_RAID_EDIT = 'raid-edit';

    /**
     * The `from` value of a Create form opened from a raid's Bosses setup step.
     */
    public const string FROM_RAID_SETUP = 'raid-setup';

    /**
     * Every `from` value a Create form accepts.
     *
     * @var list<string>
     */
    public const array FROM_RAID_PAGES = [self::FROM_RAID_EDIT, self::FROM_RAID_SETUP];

    protected function routePrefix(): string
    {
        return 'management.bosses';
    }

    protected function setupSteps(): ?string
    {
        return null;
    }

    /**
     * The Create page's URLs. Cancel leads back to the raid page the form was
     * opened from (its Bosses step or its Edit page), otherwise to the Index.
     *
     * @return array{index: string, store: string, cancel: string}
     */
    public function forCreate(?Raid $raid = null, ?string $from = null): array
    {
        return [
            ...parent::forCreate(),
            'cancel' => ($raid !== null ? $this->raidPageUrl($raid, $from) : null) ?? $this->route('index'),
        ];
    }

    /**
     * The Edit page's URLs, with the boss's raid's Edit page.
     *
     * @return array{index: string, update: string, raid: string}
     */
    public function forEdit(Model $record): array
    {
        return [
            ...parent::forEdit($record),
            'raid' => URL::route('management.raids.edit', $record->raid_id),
        ];
    }

    /**
     * Where a newly stored boss leads: back to the raid page the form was
     * opened from (its Bosses step or its Edit page),
     * otherwise the boss's Edit page.
     */
    public function afterStore(Model $record, ?string $from = null): string
    {
        return $this->raidPageUrl($record->raid_id, $from) ?? parent::afterStore($record);
    }

    /**
     * The raid page a `from` value names, or null for any other value.
     */
    private function raidPageUrl(Raid|int $raid, ?string $from): ?string
    {
        return match ($from) {
            self::FROM_RAID_SETUP => URL::route('management.raids.setup', [$raid, RaidSetupStep::Bosses]),
            self::FROM_RAID_EDIT => URL::route('management.raids.edit', $raid),
            default => null,
        };
    }
}
