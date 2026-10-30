<?php

namespace App\Http\Controllers;

use App\Actions\Discord\BuildDiscordMentionDirectory;
use App\Actions\Handbook\CreateChapter;
use App\Actions\Handbook\FindChapter;
use App\Actions\Handbook\ListChapters;
use App\Actions\Handbook\ListTrashedChapters;
use App\Actions\Handbook\ReorderChapters;
use App\Actions\Handbook\RestoreChapter;
use App\Actions\Handbook\TrashChapter;
use App\Actions\Handbook\UpdateChapter;
use App\Contracts\Actions\Handbook\StoresHandbook;
use App\Data\Handbook\ChapterData;
use App\Data\Handbook\ChapterSummaryData;
use App\Data\Handbook\TrashedChapterData;
use App\Http\Requests\ReorderHandbookChaptersRequest;
use App\Http\Requests\StoreHandbookChapterRequest;
use App\Http\Requests\UpdateHandbookChapterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('manage-handbook')]
class HandbookChapterController extends Controller implements StoresHandbook
{
    /**
     * List the live chapters, the recently deleted chapters and the mention directory.
     */
    public function index(): Response
    {
        $chapters = ListChapters::run();
        $trashed = ListTrashedChapters::run();

        return Inertia::render('Manage/Handbook/Index', [
            'chapters' => $chapters->map(fn (ChapterData $chapter): ChapterSummaryData => ChapterSummaryData::from($chapter))->values(),
            'trashed' => $trashed,
            'mentions' => BuildDiscordMentionDirectory::run(
                $chapters->pluck('content')->concat($trashed->pluck('emoji')->filter())->implode("\n"),
            ),
        ]);
    }

    /**
     * Show the form for adding a chapter.
     */
    public function create(): Response
    {
        return Inertia::render('Manage/Handbook/Create', [
            'mentions' => BuildDiscordMentionDirectory::run(),
        ]);
    }

    /**
     * Store a new chapter and continue to its Edit page.
     */
    public function store(StoreHandbookChapterRequest $request): RedirectResponse
    {
        $chapter = CreateChapter::run($request->validated('content'));

        return redirect()->route('management.handbook.edit', $chapter->slug)
            ->with('success', "Added {$chapter->title}.");
    }

    /**
     * Store the order of the chapters that follow the introduction.
     */
    public function reorder(ReorderHandbookChaptersRequest $request): RedirectResponse
    {
        ReorderChapters::run($request->validated('slugs'));

        return back();
    }

    /**
     * Show the form for editing a chapter.
     */
    public function edit(string $chapter): Response
    {
        return Inertia::render('Manage/Handbook/Edit', [
            'chapter' => $this->findOrFail($chapter),
            'mentions' => BuildDiscordMentionDirectory::run(),
        ]);
    }

    /**
     * Save the chapter's content.
     */
    public function update(UpdateHandbookChapterRequest $request, string $chapter): RedirectResponse
    {
        $existing = $this->findOrFail($chapter);

        $saved = UpdateChapter::run($chapter, $request->validated('content'), $request->validated('base_hash'));

        return back()->with('success', $saved ? "Saved {$existing->title}." : 'No changes to save.');
    }

    /**
     * Move the chapter to Recently deleted.
     */
    public function destroy(string $chapter): RedirectResponse
    {
        $existing = $this->findOrFail($chapter);

        TrashChapter::run($chapter);

        return redirect()->route('management.handbook.index')
            ->with('success', "Deleted {$existing->title}. You can restore it for ".self::TRASH_DAYS.' days.');
    }

    /**
     * Restore a chapter from Recently deleted.
     */
    public function restore(string $chapter): RedirectResponse
    {
        $trashed = ListTrashedChapters::run()->firstWhere('slug', $chapter);

        abort_unless($trashed instanceof TrashedChapterData, 404);

        RestoreChapter::run($chapter);

        return redirect()->route('management.handbook.index')
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
