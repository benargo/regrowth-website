<?php

namespace App\Services\Blizzard;

use App\Jobs\UpdateCharacterFromRoster;
use App\Models\Character;
use App\Services\Blizzard\Data\GuildMember;
use Illuminate\Support\Collection;

class GuildService extends Service
{
    protected string $realmSlug = 'thunderstrike';

    protected string $nameSlug = 'regrowth';

    protected string $basePath = '/data/wow/guild';

    protected bool $shouldUpdateCharacters = false;

    /**
     * Default cache TTL values in seconds.
     */
    protected int $cacheTtl = 900;   // 15 minutes

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
            $this->cacheTtl,
            function () use ($realmSlug, $nameSlug) {
                $roster = $this->getJson("/{$realmSlug}/{$nameSlug}/roster");

                // Dispatch character update jobs if enabled
                $this->updateCharacters($roster);

                return $roster;
            }
        );
    }

    /**
     * Set whether characters should be updated.
     */
    public function shouldUpdateCharacters(bool $shouldUpdateCharacters = true): static
    {
        $this->shouldUpdateCharacters = $shouldUpdateCharacters;

        return $this;
    }

    protected function updateCharacters(array $roster): void
    {
        if ($this->shouldUpdateCharacters) {
            foreach ($roster['members'] as $memberData) {
                UpdateCharacterFromRoster::dispatch($memberData);
            }
        }
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
