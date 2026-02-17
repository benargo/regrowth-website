<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\TogglePermissionRequest;
use App\Models\DiscordRole;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Display the permissions management page.
     */
    public function index(): Response
    {
        $discordRoles = DiscordRole::with('permissions')
            ->orderBy('position')
            ->get();

        $permissions = Permission::orderBy('name')->get();

        return Inertia::render('Dashboard/ManagePermissions', [
            'discordRoles' => $discordRoles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Toggle a permission for a Discord role.
     */
    public function toggle(TogglePermissionRequest $request): RedirectResponse
    {
        $role = DiscordRole::findOrFail($request->validated('discord_role_id'));
        $permission = Permission::findOrFail($request->validated('permission_id'));

        if ($request->validated('enabled')) {
            $role->givePermissionTo($permission);
        } else {
            $role->revokePermissionTo($permission);
        }

        return back();
    }
}
