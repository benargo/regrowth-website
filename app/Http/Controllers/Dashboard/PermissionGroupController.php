<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderPermissionGroupsRequest;
use App\Http\Requests\StorePermissionGroupRequest;
use App\Http\Requests\UpdatePermissionGroupRequest;
use App\Http\Resources\PermissionGroupResource;
use App\Models\PermissionGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('manage-permissions')]
class PermissionGroupController extends Controller
{
    /**
     * Display the permission groups in order with their permission counts.
     */
    public function index(): Response
    {
        return Inertia::render('Manage/Permissions/Groups', [
            'permissionGroups' => PermissionGroupResource::collection(
                PermissionGroup::withCount('permissions')->ordered()->get()
            ),
        ]);
    }

    /**
     * Add a permission group at the end of the list.
     */
    public function store(StorePermissionGroupRequest $request): RedirectResponse
    {
        $name = $request->validated('name');

        PermissionGroup::create(['name' => $name]);

        return back()->with('success', "Added {$name}.");
    }

    /**
     * Rename a permission group.
     */
    public function update(UpdatePermissionGroupRequest $request, PermissionGroup $permissionGroup): RedirectResponse
    {
        $name = $request->validated('name');

        $permissionGroup->update(['name' => $name]);

        return back()->with('success', "Saved {$name}.");
    }

    /**
     * Store the order of the permission groups inside a single transaction: the first id gets sort order 1.
     */
    public function reorder(ReorderPermissionGroupsRequest $request): RedirectResponse
    {
        DB::transaction(fn () => PermissionGroup::setNewOrder(array_values($request->validated('permission_group_ids'))));

        return back();
    }

    /**
     * Delete a permission group that has no permissions.
     */
    public function destroy(PermissionGroup $permissionGroup): RedirectResponse
    {
        $deleted = PermissionGroup::whereKey($permissionGroup->id)->whereDoesntHave('permissions')->delete();

        if ($deleted === 0) {
            return back()->with('error', "Move this group's permissions to another group first.");
        }

        return back()->with('success', "Deleted {$permissionGroup->name}.");
    }
}
