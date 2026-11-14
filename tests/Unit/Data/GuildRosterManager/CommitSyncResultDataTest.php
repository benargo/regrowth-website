<?php

namespace Tests\Unit\Data\GuildRosterManager;

use App\Data\GuildRosterManager\CommitSyncResultData;
use App\Enums\GuildRosterManager\CommitSyncStatus;
use App\Models\GuildRosterManager\Upload;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class CommitSyncResultDataTest extends TestCase
{
    #[Test]
    public function it_casts_the_status_string_onto_the_enum(): void
    {
        $sync = new Upload;

        $result = CommitSyncResultData::from([
            'status' => 'revision_conflict',
            'sync' => $sync,
            'revision' => 4,
        ]);

        $this->assertSame(CommitSyncStatus::RevisionConflict, $result->status);
        $this->assertSame($sync, $result->sync);
        $this->assertSame(4, $result->revision);
    }
}
