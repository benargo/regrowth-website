<?php

namespace Tests\Feature\Jobs\GuildRosterManager;

use App\Jobs\GuildRosterManager\PurgeArchivedUploads;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class PurgeArchivedUploadsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        Storage::fake('local');
    }

    #[Test]
    public function it_deletes_archived_uploads_older_than_three_months(): void
    {
        $this->putWithAge('grm/archives/anniversary/2026-01-01_12-00-00.csv', now()->subMonths(3)->subDay());

        (new PurgeArchivedUploads)->handle(Storage::disk('local'));

        Storage::disk('local')->assertMissing('grm/archives/anniversary/2026-01-01_12-00-00.csv');
    }

    #[Test]
    public function it_deletes_old_archives_stored_outside_a_game_version_directory(): void
    {
        $this->putWithAge('grm/archives/2026-01-01_12-00-00.csv', now()->subMonths(3)->subDay());

        (new PurgeArchivedUploads)->handle(Storage::disk('local'));

        Storage::disk('local')->assertMissing('grm/archives/2026-01-01_12-00-00.csv');
    }

    #[Test]
    public function it_keeps_archived_uploads_within_three_months(): void
    {
        $this->putWithAge('grm/archives/anniversary/2026-09-01_12-00-00.csv', now()->subMonths(3)->addDay());

        (new PurgeArchivedUploads)->handle(Storage::disk('local'));

        Storage::disk('local')->assertExists('grm/archives/anniversary/2026-09-01_12-00-00.csv');
    }

    #[Test]
    public function it_never_touches_the_latest_upload(): void
    {
        $this->putWithAge('grm/uploads/anniversary/latest.csv', now()->subYear());

        (new PurgeArchivedUploads)->handle(Storage::disk('local'));

        Storage::disk('local')->assertExists('grm/uploads/anniversary/latest.csv');
    }

    private function putWithAge(string $path, \DateTimeInterface $modifiedAt): void
    {
        $disk = Storage::disk('local');
        $disk->put($path, 'Name,Rank');
        touch($disk->path($path), $modifiedAt->getTimestamp());
    }
}
