<?php

namespace App\Http\Controllers;

use App\Actions\Discord\BuildDiscordMentionDirectory;
use App\Actions\RaidInformation\ListChapters;
use App\Contracts\Actions\RaidInformation\StoresRaidInformation;
use App\Data\RaidInformation\ChapterData;
use App\Data\RaidInformation\ChapterSummaryData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

#[Middleware('auth')]
#[Authorize('view-raid-information')]
class RaidInformationController extends Controller implements StoresRaidInformation
{
    /**
     * Display the introduction chapter.
     */
    public function index(): Response
    {
        return $this->render(self::INTRODUCTION);
    }

    /**
     * Display a chapter; the introduction slug redirects to the index.
     */
    public function show(string $chapter): Response|RedirectResponse
    {
        if ($chapter === self::INTRODUCTION) {
            return redirect()->route('raiding.information.index');
        }

        return $this->render($chapter);
    }

    /**
     * Render the reader page for a chapter with its neighbours and mention directory.
     */
    private function render(string $slug): Response
    {
        $chapters = ListChapters::run();
        $position = $chapters->search(fn (ChapterData $chapter): bool => $chapter->slug === $slug);

        abort_if($position === false, 404);

        return Inertia::render('Raiding/Information/Show', [
            'chapters' => $chapters->map(fn (ChapterData $chapter): ChapterSummaryData => ChapterSummaryData::from($chapter))->values(),
            'chapter' => $chapters->get($position),
            'previous' => $this->summary($chapters, $position - 1),
            'next' => $this->summary($chapters, $position + 1),
            'mentions' => BuildDiscordMentionDirectory::run($chapters->pluck('content')->implode("\n")),
        ]);
    }

    /**
     * Summarise the chapter at the given position, or return null when there is none.
     *
     * @param  Collection<int, ChapterData>  $chapters
     */
    private function summary(Collection $chapters, int $position): ?ChapterSummaryData
    {
        $chapter = $chapters->get($position);

        return $chapter === null || $position < 0 ? null : ChapterSummaryData::from($chapter);
    }
}
