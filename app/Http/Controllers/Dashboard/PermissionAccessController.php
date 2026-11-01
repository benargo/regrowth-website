<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Permissions\SetDiscordRolePermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePermissionAccessRequest;
use App\Models\DiscordRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('manage-permissions')]
class PermissionAccessController extends Controller
{
    /**
     * Grant or revoke the given permissions for a Discord role.
     */
    public function __invoke(UpdatePermissionAccessRequest $request): RedirectResponse
    {
        SetDiscordRolePermissions::run(
            DiscordRole::findOrFail($request->validated('discord_role_id')),
            $request->validated('permission_ids'),
            $request->boolean('enabled'),
        );

        return back();
    }
}
