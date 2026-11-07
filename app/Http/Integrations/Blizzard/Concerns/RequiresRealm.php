<?php

namespace App\Http\Integrations\Blizzard\Concerns;

use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Exceptions\RealmRequiredException;
use Illuminate\Support\Str;
use Saloon\Http\PendingRequest;

trait RequiresRealm
{
    public function bootRequiresRealm(PendingRequest $pendingRequest): void
    {
        $namespace = $this->namespace ?? BlizzardNamespace::default();

        if ($this->realm === null && $namespace->requiresRealm()) {
            throw new RealmRequiredException($namespace);
        }
    }

    protected static function normalizeRealm(?string $realm): ?string
    {
        return $realm !== null ? Str::slug($realm) : null;
    }

    /**
     * Build the `{realm}/{name}` path segment, or just `{name}` when there is no
     * realm or the namespace does not use realms.
     */
    protected function realmAndName(string $name): string
    {
        $namespace = $this->namespace ?? BlizzardNamespace::default();

        if ($this->realm === null || ! $namespace->requiresRealm()) {
            return $name;
        }

        return "{$this->realm}/{$name}";
    }
}
