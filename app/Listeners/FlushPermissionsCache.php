<?php

namespace App\Listeners;

use App\Contracts\Events\FlushesPermissionsCache;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

class FlushPermissionsCache implements ShouldBeUnique
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected PermissionRegistrar $permissionRegistrar
    ) {}

    /**
     * Handle the event.
     */
    public function handle(FlushesPermissionsCache $event): void
    {
        Cache::tags(['permissions'])->flush();
        $this->permissionRegistrar->forgetCachedPermissions();
    }
}
