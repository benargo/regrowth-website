<?php

namespace App\Http\Controllers;

use App\Actions\Discord\BuildDiscordMentionDirectory;
use App\Actions\RaidInformation\CreateChapter;
use App\Actions\RaidInformation\FindChapter;
use App\Actions\RaidInformation\ListChapters;
use App\Actions\RaidInformation\ListTrashedChapters;
use App\Actions\RaidInformation\ReorderChapters;
use App\Actions\RaidInformation\RestoreChapter;
use App\Actions\RaidInformation\TrashChapter;
use App\Actions\RaidInformation\UpdateChapter;
use App\Contracts\Actions\RaidInformation\StoresRaidInformation;
use App\Data\RaidInformation\ChapterData;
use App\Data\RaidInformation\ChapterSummaryData;
use App\Data\RaidInformation\TrashedChapterData;
use App\Http\Requests\ReorderRaidInformationChaptersRequest;
use App\Http\Requests\StoreRaidInformationChapterRequest;
use App\Http\Requests\UpdateRaidInformationChapterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('manage-raid-information')]
class RaidInformationChapterController extends Controller
{
    /**
     * List the live chapters, the recently deleted chapters and the mention directory.
     */
    public function index(): Response
    {
        $chapters = ListChapters::run();

        return Inertia::render('Manage/RaidInformation/Index', [
            'chapters' => $chapters->map(fn (ChapterData $chapter): ChapterSummaryData => ChapterSummaryData::from($chapter))->values(),
            'trashed' => ListTrashedChapters::run(),
            'mentions' => BuildDiscordMentionDirectory::run($chapters->pluck('content')->implode("\n")),
        ]);
    }

    /**
     * Show the form for adding a chapter.
     */
    public function create(): Response
    {
        return Inertia::render('Manage/RaidInformation/Create', [
            'mentions' => BuildDiscordMentionDirectory::run(),
        ]);
    }

    /**
     * Store a new chapter and continue to its Edit page.
     */
    public function store(StoreRaidInformationChapterRequest $request): RedirectResponse
    {
        $chapter = CreateChapter::run($request->validated('content'));

        return Redirect::route('management.raid-information.edit', $chapter->slug)
            ->with('success', "Added {$chapter->title}.");
    }

    /**
     * Store the order of the chapters that follow the introduction.
     */
    public function reorder(ReorderRaidInformationChaptersRequest $request): RedirectResponse
    {
        ReorderChapters::run($request->validated('slugs'));

        return Redirect::back();
    }

    /**
     * Show the form for editing a chapter.
     */
    public function edit(string $chapter): Response
    {
        return Inertia::render('Manage/RaidInformation/Edit', [
            'chapter' => $this->findOrFail($chapter),
            'mentions' => BuildDiscordMentionDirectory::run(),
        ]);
    }

    /**
     * Save the chapter's content.
     */
    public function update(UpdateRaidInformationChapterRequest $request, string $chapter): RedirectResponse
    {
        $existing = $this->findOrFail($chapter);

        $saved = UpdateChapter::run($chapter, $request->validated('content'), $request->validated('base_hash'));

        return Redirect::back()->with('success', $saved ? "Saved {$existing->title}." : 'No changes to save.');
    }

    /**
     * Move the chapter to Recently deleted.
     */
    public function destroy(string $chapter): RedirectResponse
    {
        $existing = $this->findOrFail($chapter);

        TrashChapter::run($chapter);

        return Redirect::route('management.raid-information.index')
            ->with('success', "Deleted {$existing->title}. You can restore it for ".StoresRaidInformation::TRASH_DAYS.' days.');
    }

    /**
     * Restore a chapter from Recently deleted.
     */
    public function restore(string $chapter): RedirectResponse
    {
        $trashed = ListTrashedChapters::run()->firstWhere('slug', $chapter);

        abort_unless($trashed instanceof TrashedChapterData, 404);

        RestoreChapter::run($chapter);

        return Redirect::route('management.raid-information.index')
            ->with('success', "Restored {$trashed->title}.");
    }

    /**
     * Find a live chapter or abort with a 404.
     */
    private function findOrFail(string $slug): ChapterData
    {
        return FindChapter::run($slug) ?? abort(404);
    }
}
