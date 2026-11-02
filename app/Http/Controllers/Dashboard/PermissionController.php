<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePermissionRequest;
use App\Http\Resources\DiscordRoleResource;
use App\Http\Resources\PermissionGroupResource;
use App\Http\Resources\PermissionResource;
use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\PermissionGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('manage-permissions')]
class PermissionController extends Controller
{
    /**
     * Display the permissions access tab for one Discord role at a time.
     */
    public function index(Request $request): Response
    {
        $discordRoles = DiscordRole::grantingPermissions()
            ->withCount('permissions')
            ->orderByDesc('position')
            ->get();

        $requestedRoleId = $request->string('role')->toString();
        $selectedRoleId = $discordRoles->contains('id', $requestedRoleId)
            ? $requestedRoleId
            : $discordRoles->first()?->id;

        $orderedPermissions = fn ($query) => $query
            ->with('discordRoles:id')
            ->orderByRaw('COALESCE(label, name)');

        return Inertia::render('Manage/Permissions/Access', [
            'discordRoles' => DiscordRoleResource::collection($discordRoles),
            'selectedRoleId' => $selectedRoleId,
            'permissionGroups' => PermissionGroupResource::collection(
                PermissionGroup::ordered()
                    ->whereHas('permissions')
                    ->with(['permissions' => $orderedPermissions])
                    ->get()
            ),
            'ungroupedPermissions' => PermissionResource::collection(
                $orderedPermissions(Permission::whereNull('permission_group_id'))->get()
            ),
        ]);
    }

    /**
     * Display the permissions tab listing every group and its permissions.
     */
    public function manage(): Response
    {
        $orderedPermissions = fn ($query) => $query
            ->withCount(['discordRoles' => fn (Builder $query) => $query->where('grants_permissions', true)])
            ->orderByRaw('COALESCE(label, name)');

        return Inertia::render('Manage/Permissions/Manage', [
            'permissionGroups' => PermissionGroupResource::collection(
                PermissionGroup::ordered()
                    ->with(['permissions' => $orderedPermissions])
                    ->get()
            ),
            'ungroupedPermissions' => PermissionResource::collection(
                $orderedPermissions(Permission::whereNull('permission_group_id'))->get()
            ),
        ]);
    }

    /**
     * Update a permission's label, description and group.
     */
    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->update($request->validated());

        return back()->with('success', "Saved {$permission->label}.");
    }
}
