<?php

namespace App\Http\Controllers\GuildRosterManager;

use App\Data\GuildRosterManager\RosterFreshnessData;
use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuildRosterManager\SyncDashboardRequest;
use App\Http\Resources\GuildRosterManager\UploadResource;
use App\Http\Resources\GuildRosterManager\UploadRowResource;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Upload;
use App\Models\GuildRosterManager\UploadRow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class SyncDashboardController extends Controller
{
    /**
     * Show each current roster's freshness, the newest uploads, and the
     * selected upload's row outcomes.
     */
    public function index(SyncDashboardRequest $request): Response
    {
        $selectedUpload = $request->validated('upload') ?? Upload::latest('captured_at')->value('id');
        $reason = $request->reason();

        return Inertia::render('Manage/GRM/Sync', [
            'rosters' => Inertia::defer(fn (): Collection => $this->rosters(), 'rosters'),
            'uploads' => UploadResource::collection(
                Upload::with(['device', 'gameVersion', 'user'])->latest('captured_at')->limit(20)->get(),
            ),
            'selectedUpload' => $selectedUpload,
            'selectedReason' => $reason,
            'reasons' => collect(RowReason::cases())
                ->map(fn (RowReason $case): array => ['value' => $case, 'isExpected' => $case->isExpected()])
                ->all(),
            'rows' => fn (): AnonymousResourceCollection => $this->rows($selectedUpload, $reason),
        ]);
    }

    /**
     * Describe each current roster's game version, upload status and newest accepted upload.
     *
     * @return Collection<int, RosterFreshnessData>
     */
    private function rosters(): Collection
    {
        return RosterFreshnessData::collect(
            GameVersion::currentRosters()->load(['media', 'currentRosterRevision'])->values(),
        );
    }

    /**
     * Page through the upload's row outcomes, optionally limited to one reason: failed and warning rows with
     * unexpected reasons first, then other unexpected reasons, then expected reasons, each by name. Page links
     * keep the upload, including one chosen by default.
     */
    private function rows(?string $uploadId, ?RowReason $reason): AnonymousResourceCollection
    {
        $expectedReasons = collect(RowReason::cases())
            ->filter(fn (RowReason $case): bool => $case->isExpected())
            ->map(fn (RowReason $case): string => $case->value)
            ->values();
        $expectedPlaceholders = $expectedReasons->map(fn (): string => '?')->implode(', ');

        $rows = UploadRow::with('character')
            ->where('upload_id', $uploadId)
            ->when($reason, fn ($query) => $query->where('reason', $reason))
            ->orderByRaw(
                "case when reason in ({$expectedPlaceholders}) then 2 when status in (?, ?) then 0 else 1 end",
                [...$expectedReasons->all(), RowStatus::Failed->value, RowStatus::Warning->value],
            )
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString()
            ->appends(array_filter(['upload' => $uploadId]));

        return UploadRowResource::collection($rows);
    }
}
