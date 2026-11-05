<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Discord\BuildDiscordMentionDirectory;
use App\Actions\RecruitmentMacros\CreateRecruitmentMacro;
use App\Actions\RecruitmentMacros\ReorderRecruitmentMacros;
use App\Actions\RecruitmentMacros\RestoreRecruitmentMacro;
use App\Contracts\Models\TrashesRecruitmentMacros;
use App\Enums\RecruitmentMacroType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderRecruitmentMacrosRequest;
use App\Http\Requests\StoreRecruitmentMacroRequest;
use App\Http\Requests\UpdateRecruitmentMacroRequest;
use App\Http\Resources\RecruitmentMacroResource;
use App\Models\RecruitmentMacro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentMacroController extends Controller implements TrashesRecruitmentMacros
{
    /**
     * List the live macros in order, the trashed macros the user can restore, and the mention directory.
     */
    #[Authorize('viewAny', RecruitmentMacro::class)]
    public function index(Request $request): Response
    {
        $macros = RecruitmentMacro::with('createdBy.discordRoles')->ordered()->get();

        $trashed = RecruitmentMacro::onlyTrashed()
            ->with('createdBy.discordRoles')
            ->latest('deleted_at')
            ->get()
            ->filter(fn (RecruitmentMacro $macro): bool => $request->user()->can('restore', $macro))
            ->values();

        return Inertia::render('Manage/RecruitmentMacros/Index', [
            'macros' => RecruitmentMacroResource::collection($macros),
            'trashed' => RecruitmentMacroResource::collection($trashed),
            'mentions' => BuildDiscordMentionDirectory::run(
                $macros->where('type', RecruitmentMacroType::Discord)->pluck('content')->implode("\n"),
            ),
        ]);
    }

    /**
     * Show the form for adding a macro.
     */
    #[Authorize('create', RecruitmentMacro::class)]
    public function create(): Response
    {
        return Inertia::render('Manage/RecruitmentMacros/Create', [
            'types' => $this->types(),
            'mentions' => BuildDiscordMentionDirectory::run(),
        ]);
    }

    /**
     * Store a new macro against the current user and continue to its Edit page.
     */
    #[Authorize('create', RecruitmentMacro::class)]
    public function store(StoreRecruitmentMacroRequest $request): RedirectResponse
    {
        $macro = CreateRecruitmentMacro::run($request->user(), $request->validated());

        return redirect()->route('management.recruitment-macros.edit', $macro)
            ->with('success', "Added {$macro->title}.");
    }

    /**
     * Store the order of the live macros.
     */
    #[Authorize('reorder', RecruitmentMacro::class)]
    public function reorder(ReorderRecruitmentMacrosRequest $request): RedirectResponse
    {
        ReorderRecruitmentMacros::run($request->validated('macro_ids'));

        return back();
    }

    /**
     * Show the form for editing a macro.
     */
    #[Authorize('update', 'recruitmentMacro')]
    public function edit(RecruitmentMacro $recruitmentMacro): Response
    {
        return Inertia::render('Manage/RecruitmentMacros/Edit', [
            'macro' => new RecruitmentMacroResource($recruitmentMacro->load('createdBy.discordRoles')),
            'types' => $this->types(),
            'mentions' => BuildDiscordMentionDirectory::run(),
        ]);
    }

    /**
     * Save the macro's title, type and content.
     */
    #[Authorize('update', 'recruitmentMacro')]
    public function update(UpdateRecruitmentMacroRequest $request, RecruitmentMacro $recruitmentMacro): RedirectResponse
    {
        $recruitmentMacro->update($request->validated());

        return back()->with('success', "Saved {$recruitmentMacro->title}.");
    }

    /**
     * Move the macro to Recently deleted.
     */
    #[Authorize('delete', 'recruitmentMacro')]
    public function destroy(RecruitmentMacro $recruitmentMacro): RedirectResponse
    {
        $recruitmentMacro->delete();

        return redirect()->route('management.recruitment-macros.index')
            ->with('success', "Deleted {$recruitmentMacro->title}. You can restore it for ".self::TRASH_DAYS.' days.');
    }

    /**
     * Restore a macro from Recently deleted to the end of the order.
     */
    #[Authorize('restore', 'recruitmentMacro')]
    public function restore(RecruitmentMacro $recruitmentMacro): RedirectResponse
    {
        abort_unless($recruitmentMacro->trashed(), 404);

        RestoreRecruitmentMacro::run($recruitmentMacro);

        return redirect()->route('management.recruitment-macros.index')
            ->with('success', "Restored {$recruitmentMacro->title}.");
    }

    /**
     * Get the macro types offered by the form, with their labels and character limits.
     *
     * @return list<array{value: string, label: string, character_limit: int}>
     */
    private function types(): array
    {
        return collect(RecruitmentMacroType::cases())
            ->map(fn (RecruitmentMacroType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'character_limit' => $type->characterLimit(),
            ])
            ->all();
    }
}
