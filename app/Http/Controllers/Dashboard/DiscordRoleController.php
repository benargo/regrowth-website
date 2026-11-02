<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Discord\UpdateDiscordRoleFlags;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDiscordRoleRequest;
use App\Http\Resources\DiscordRoleResource;
use App\Models\DiscordRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('manage-permissions')]
class DiscordRoleController extends Controller
{
    /**
     * Display every Discord role with its user and permission counts.
     */
    public function index(): Response
    {
        return Inertia::render('Manage/DiscordRoles/Index', [
            'discordRoles' => DiscordRoleResource::collection(
                DiscordRole::withCount(['users', 'permissions'])->orderByDesc('position')->get()
            ),
        ]);
    }

    /**
     * Update a Discord role's permission-granting and visibility flags.
     */
    public function update(UpdateDiscordRoleRequest $request, DiscordRole $discordRole): RedirectResponse
    {
        UpdateDiscordRoleFlags::run($discordRole, $request->validated());

        return back();
    }
}
