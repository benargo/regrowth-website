<?php

namespace App\Services\Blizzard;

use App\Services\Blizzard\Data\GuildMember;
use Illuminate\Support\Collection;

class GuildService extends Service
{
    protected string $realmSlug = 'thunderstrike';

    protected string $nameSlug = 'regrowth';

    protected string $basePath = '/data/wow/guild';

    /**
     * Default cache TTL values in seconds.
     */
    protected const CACHE_TTL_ROSTER = 10800;   // 3 hours

    public function __construct(
        protected Client $client,
    ) {
        parent::__construct($client->withNamespace('profile-classicann-eu'));
    }

    /**
     * Get guild roster by guild name and realm slug.
     */
    public function roster(?string $realmSlug = null, ?string $nameSlug = null): array
    {
        $realmSlug = $realmSlug ?? $this->realmSlug;
        $nameSlug = $nameSlug ?? $this->nameSlug;

        return $this->cacheable(
            $this->guildRosterCacheKey($realmSlug, $nameSlug),
            self::CACHE_TTL_ROSTER,
            function () use ($realmSlug, $nameSlug) {
                return $this->getJson("/{$realmSlug}/{$nameSlug}/roster");
            }
        );
    }

    /**
     * Get guild members as GuildMember objects.
     *
     * @return Collection<int, GuildMember>
     */
    public function members(?string $realmSlug = null, ?string $nameSlug = null): Collection
    {
        $rosterData = $this->roster($realmSlug, $nameSlug);

        return collect($rosterData['members'] ?? [])
            ->map(fn (array $memberData) => GuildMember::fromArray($memberData));
    }

    /**
     * Generate cache key for guild roster.
     */
    protected function guildRosterCacheKey(string $realmSlug, string $nameSlug): string
    {
        return sprintf(
            'blizzard.guild.roster.%s.%s.%s',
            $realmSlug,
            $nameSlug,
            $this->getNamespace()
        );
    }
}
