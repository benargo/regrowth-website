<?php

namespace Tests\Feature\GuildRosterManager;

use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Enums\GuildRosterManager\UploadOutcome;
use App\Models\Character;
use App\Models\DesktopClient;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\GuildRosterManager\RosterRevision;
use App\Models\GuildRosterManager\Upload;
use App\Models\GuildRosterManager\UploadRow;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\Support\DashboardTestCase;

#[Group('characters')]
class SyncDashboardControllerTest extends DashboardTestCase
{
    use MocksBlizzardServices;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameVersion = GameVersion::factory()->fetchableRoster()->create(['slug' => 'anniversary']);
        $this->mockGetGuild(['member_count' => 1]);
        $this->applyBlizzardMocks();
    }

    #[Group('happy-path')]
    #[Test]
    public function it_shows_rosters_uploads_and_rows_to_officers(): void
    {
        $device = DesktopClient::factory()->for($this->officer)->create(['name' => 'Raid PC']);
        $older = Upload::factory()->for($this->gameVersion)->for($this->officer)
            ->withOutcome(UploadOutcome::Processed)
            ->create(['captured_at' => now()->subDay(), 'processed_count' => 3]);
        $newest = Upload::factory()->for($this->gameVersion)->for($this->officer)->desktop()
            ->withOutcome(UploadOutcome::Processed)
            ->create(['desktop_client_id' => $device->id, 'captured_at' => now()->subHour(), 'member_count' => 1, 'processed_count' => 7, 'skipped_count' => 2]);
        Upload::factory()->for($this->gameVersion)->for($this->officer)->staging($device)->create(['captured_at' => now()]);
        $character = Character::factory()->create(['name' => 'Thrall']);
        UploadRow::factory()->for($newest)->create(['name' => 'Thrall', 'character_id' => $character->id]);
        UploadRow::factory()->for($older)->create(['name' => 'Jaina']);

        $response = $this->actingAs($this->officer)->get(route('management.grm.sync', ['upload' => $newest->id]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/GRM/Sync')
            ->where('selectedUpload', $newest->id)
            ->has('uploads.data', 3)
            ->where('uploads.data.1.id', $newest->id)
            ->where('uploads.data.1.source', 'desktop')
            ->where('uploads.data.1.outcome', 'processed')
            ->where('uploads.data.1.device.name', 'Raid PC')
            ->where('uploads.data.1.uploader.name', $this->officer->display_name)
            ->where('uploads.data.1.processedCount', 7)
            ->where('uploads.data.1.skippedCount', 2)
            ->where('uploads.data.1.gameVersion.slug', 'anniversary')
            ->where('uploads.data.0.outcome', 'staging')
            ->has('rows.data', 1)
            ->where('rows.data.0.name', 'Thrall')
            ->where('rows.data.0.status', 'processed')
            ->where('rows.data.0.reason', 'updated')
            ->where('rows.data.0.characterId', $character->id)
            ->where('rows.data.0.characterSlug', $character->slug)
            ->where('rows.data.0.meta.is_expected', false)
            ->missing('rosters')
            ->loadDeferredProps('rosters', fn (Assert $reload) => $reload
                ->has('rosters', 1)
                ->where('rosters.0.gameVersion.slug', 'anniversary')
                ->where('rosters.0.status', 'current')
                ->where('rosters.0.lastUpload.id', $newest->id)
                ->where('rosters.0.lastUpload.device.name', 'Raid PC')
                ->where('rosters.0.rankNameDifferences', [])
            )
        );
    }

    #[Test]
    public function it_selects_the_newest_upload_by_default(): void
    {
        Upload::factory()->for($this->gameVersion)->create(['captured_at' => now()->subDay()]);
        $newest = Upload::factory()->for($this->gameVersion)->create(['captured_at' => now()]);

        $response = $this->actingAs($this->officer)->get(route('management.grm.sync'));

        $response->assertInertia(fn (Assert $page) => $page->where('selectedUpload', $newest->id));
    }

    #[Test]
    public function it_shows_the_rank_name_differences_for_each_roster(): void
    {
        GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => 0, 'name' => 'Guild Master']);
        GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => 1, 'name' => 'Officer']);
        RosterRevision::factory()->for($this->gameVersion)->create(['revision' => 1, 'ranks' => ['Guild Master', 'Captain']]);

        $response = $this->actingAs($this->officer)->get(route('management.grm.sync'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('rosters', fn (Assert $reload) => $reload
                ->where('rosters.0.rankNameDifferences', [
                    ['sortOrder' => 1, 'gameName' => 'Captain', 'siteName' => 'Officer'],
                ])
            )
        );
    }

    #[Test]
    public function it_filters_rows_by_reason(): void
    {
        $upload = Upload::factory()->for($this->gameVersion)->create();
        UploadRow::factory()->for($upload)->create(['name' => 'Thrall', 'status' => RowStatus::Skipped, 'reason' => RowReason::NotTracked]);
        UploadRow::factory()->for($upload)->create(['name' => 'Jaina', 'reason' => RowReason::Created]);

        $response = $this->actingAs($this->officer)->get(route('management.grm.sync', ['upload' => $upload->id, 'reason' => 'not_tracked']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 1)
            ->where('rows.data.0.name', 'Thrall')
            ->where('rows.data.0.meta.is_expected', true)
        );
    }

    #[Test]
    public function it_orders_unexpected_rows_first(): void
    {
        $upload = Upload::factory()->for($this->gameVersion)->create();
        UploadRow::factory()->for($upload)->create(['name' => 'Anduin', 'status' => RowStatus::Skipped, 'reason' => RowReason::BelowMinimumLevel]);
        UploadRow::factory()->for($upload)->create(['name' => 'Zul', 'status' => RowStatus::Failed, 'reason' => RowReason::UnknownRank]);
        UploadRow::factory()->for($upload)->create(['name' => 'Baine', 'reason' => RowReason::Updated]);
        UploadRow::factory()->for($upload)->create(['name' => 'Yrel', 'status' => RowStatus::Warning, 'reason' => RowReason::NameClash]);
        UploadRow::factory()->for($upload)->create(['name' => 'Cairne', 'status' => RowStatus::Skipped, 'reason' => RowReason::PlaceholderName]);

        $response = $this->actingAs($this->officer)->get(route('management.grm.sync', ['upload' => $upload->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.name', 'Yrel')
            ->where('rows.data.1.name', 'Zul')
            ->where('rows.data.2.name', 'Baine')
            ->where('rows.data.3.name', 'Anduin')
            ->where('rows.data.4.name', 'Cairne')
        );
    }

    #[Test]
    public function it_keeps_the_default_upload_in_the_page_links(): void
    {
        $upload = Upload::factory()->for($this->gameVersion)->create();
        UploadRow::factory()->count(51)->for($upload)->create();

        $response = $this->actingAs($this->officer)->get(route('management.grm.sync'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('rows.links.next', fn (string $url): bool => str_contains($url, 'upload='.$upload->id))
            ->where('rows.meta.links', fn (Collection $links): bool => $links->contains(
                fn (array $link): bool => str_contains((string) $link['url'], 'upload='.$upload->id)
            ))
        );
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_members(): void
    {
        $response = $this->actingAs(User::factory()->member()->create())->get(route('management.grm.sync'));

        $response->assertForbidden();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_unknown_reason(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.grm.sync', ['reason' => 'nonsense']));

        $response->assertInvalid('reason');
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_upload_that_is_not_a_uuid(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.grm.sync', ['upload' => 'nonsense']));

        $response->assertInvalid('upload');
    }
}
