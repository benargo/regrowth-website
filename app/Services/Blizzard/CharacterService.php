<?php

namespace App\Services\Blizzard;

use Illuminate\Support\Str;

class CharacterService extends Service
{
    protected string $realmSlug = 'thunderstrike';

    protected string $basePath = '/profile/wow/character';

    /**
     * Default cache TTL values in seconds.
     */
    protected int $cacheTtl = 21600; // 6 hours

    public function __construct(
        protected Client $client,
    ) {
        parent::__construct($client->withNamespace('profile-classicann-eu'));
    }

    /**
     * Get character profile by realm and name.
     */
    public function getProfile(string $name, ?string $realm = null): array
    {
        if ($realm === null) {
            $realm = $this->realmSlug;
        }

        $endpoint = sprintf('/%s/%s', Str::slug($realm), Str::lower($name));

        return $this->cacheable(
            $this->characterProfileCacheKey($realm, $name),
            $this->cacheTtl,
            fn () => $this->getJson($endpoint)
        );
    }

    /**
     * Get the cache key for a character profile.
     */
    protected function characterProfileCacheKey(string $realm, string $name): string
    {
        return sprintf(
            'blizzard.character.profile.%s.%s.%s',
            Str::slug($realm),
            Str::lower($name),
            $this->getNamespace()
        );
    }

    /**
     * Get character status by realm and name.
     */
    public function getStatus(string $name, ?string $realm = null): array
    {
        if ($realm === null) {
            $realm = $this->realmSlug;
        }

        $endpoint = sprintf('/%s/%s/status', Str::slug($realm), Str::lower($name));

        return $this->cacheable(
            $this->characterStatusCacheKey($realm, $name),
            $this->cacheTtl,
            fn () => $this->getJson($endpoint)
        );
    }

    /**
     * Get the cache key for a character profile status.
     */
    protected function characterStatusCacheKey(string $realm, string $name): string
    {
        return sprintf(
            'blizzard.character.status.%s.%s.%s',
            Str::slug($realm),
            Str::lower($name),
            $this->getNamespace()
        );
    }
}
