<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\TogglePermissionRequest;
use App\Models\DiscordRole;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    /**
     * Display the permissions management page.
     */
    public function index(Request $request): RedirectResponse
    {
        $firstPermissionGroup = Cache::tags(['permissions'])->remember('permissions:first_group', now()->addMinutes(5), function () {
            return Permission::whereNotNull('group')->orderBy('group')->value('group');
        });

        return redirect()->route('dashboard.permissions.group.show', ['group' => $firstPermissionGroup]);
    }

    /**
     * Display permissions for a specific group and the list of Discord roles for management.
     */
    public function showGroup(string $group): Response
    {
        // Cache the list of permission groups for 5 minutes to reduce database queries.
        $permissionGroups = Cache::tags(['permissions'])->remember('permissions:groups', now()->addMinutes(5), function () use ($group) {
            $groups = Permission::whereNotNull('group')->distinct('group')->pluck('group');

            // Transform the groups into a format suitable for the frontend, marking the active group.
            return $groups->transform(function ($item) use ($group) {
                return [
                    'name' => Str::headline($item),
                    'slug' => $item,
                    'active' => $item === $group,
                ];
            });
        });

        // If the requested group is not in the list of groups, throw a 404 error.
        if ($permissionGroups->pluck('slug')->doesntContain($group)) {
            abort(404, 'Permission group not found.');
        }

        // Cache the list of visible Discord roles for 5 minutes to reduce database queries.
        $discordRoles = Cache::tags(['discord', 'permissions'])->remember('discord_roles:permissions', now()->addMinutes(5), function () {
            return DiscordRole::where('is_visible', true)
                ->with('permissions')
                ->orderByDesc('position')
                ->get();
        });

        // Do not cache these as they are managed through the dashboard and may change frequently.
        $permissions = Permission::where('group', $group)->get()->toArray();

        return Inertia::render('Dashboard/ManagePermissions', [
            'discordRoles' => $discordRoles,
            'groups' => $permissionGroups,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Toggle a permission for a Discord role.
     */
    public function update(string $group, Permission $permission, TogglePermissionRequest $request): RedirectResponse
    {
        $role = DiscordRole::findOrFail($request->validated('discord_role_id'));

        $user = $request->user();

        if (! $user->is_admin && $user->highestRole()?->is($role)) {
            abort(403, 'You cannot modify permissions for your own highest role.');
        }

        if ($request->validated('enabled')) {
            $role->givePermissionTo($permission);
        } else {
            $role->revokePermissionTo($permission);
        }

        Cache::tags(['discord', 'permissions'])->forget('discord_roles:permissions');

        return back();
    }
}
