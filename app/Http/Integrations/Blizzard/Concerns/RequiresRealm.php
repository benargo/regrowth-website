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
}
