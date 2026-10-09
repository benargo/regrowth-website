<?php

namespace App\Http\Controllers\WarcraftLogs;

use App\Actions\WarcraftLogs\DeleteGuild;
use App\Http\Controllers\Controller;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Http\Requests\WarcraftLogs\StoreGuildRequest;
use App\Http\Requests\WarcraftLogs\UpdateGuildRequest;
use App\Http\Resources\WarcraftLogs\GuildResource;
use App\Jobs\WarcraftLogs\FetchGuildTags;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

class GuildController extends Controller
{
    /**
     * List every Warcraft Logs guild with its game versions and tag count.
     */
    #[Authorize('viewAny', Guild::class)]
    public function index(Request $request): Response
    {
        $guilds = Guild::with(['gameVersions' => fn (HasMany $query) => $query->orderBy('release_date')])
            ->withCount('guildTags')
            ->orderBy('id')
            ->get();

        return Inertia::render('Manage/WarcraftLogs/Guilds/Index', [
            'guilds' => GuildResource::collection($guilds)->resolve($request),
        ]);
    }

    /**
     * Show the form for adding a guild.
     */
    #[Authorize('create', Guild::class)]
    public function create(): Response
    {
        return Inertia::render('Manage/WarcraftLogs/Guilds/Create', [
            'namespaces' => WarcraftLogsNamespace::options(),
        ]);
    }

    /**
     * Add a guild, then fetch its tags from Warcraft Logs in the background
     * so they appear on its page without waiting for the scheduled sync.
     */
    #[Authorize('create', Guild::class)]
    public function store(StoreGuildRequest $request): RedirectResponse
    {
        $guild = Guild::create($request->validated());

        FetchGuildTags::dispatch($guild);

        return redirect()->route('management.warcraftlogs.guilds.show', $guild)
            ->with('success', "Added Warcraft Logs guild {$guild->id}.");
    }

    /**
     * Show a guild with its tags, and how many game versions and reports
     * deleting it would detach and how many tags it would delete.
     */
    #[Authorize('view', 'guild')]
    public function show(Request $request, Guild $guild): Response
    {
        $guild->load([
            'gameVersions' => fn (HasMany $query) => $query->orderBy('release_date'),
            'guildTags' => fn (HasMany $query) => $query->orderBy('name')->orderBy('id'),
        ])->loadCount(['gameVersions', 'guildTags', 'reports']);

        return Inertia::render('Manage/WarcraftLogs/Guilds/Show', [
            'guild' => GuildResource::make($guild)->resolve($request),
            'namespaces' => WarcraftLogsNamespace::options(),
        ]);
    }

    /**
     * Change the guild's namespace. Guild flushes the cached Warcraft Logs
     * responses when it does.
     */
    #[Authorize('update', 'guild')]
    public function update(UpdateGuildRequest $request, Guild $guild): RedirectResponse
    {
        $guild->update($request->validated());

        return back()->with('success', "Saved Warcraft Logs guild {$guild->id}.");
    }

    /**
     * Delete the guild and its tags, detaching its game versions and reports
     * rather than deleting them.
     */
    #[Authorize('delete', 'guild')]
    public function destroy(Guild $guild): RedirectResponse
    {
        DeleteGuild::run($guild);

        return redirect()->route('management.warcraftlogs.guilds.index')
            ->with('success', "Deleted Warcraft Logs guild {$guild->id}.");
    }
}
