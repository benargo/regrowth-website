<?php

namespace Tests\Unit\Events\Broadcasts;

use App\Events\Broadcasts\GrmUploadCompleted;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('broadcasting')]
class GrmUploadCompletedTest extends TestCase
{
    #[Test]
    public function it_broadcasts_only_the_counts(): void
    {
        $event = new GrmUploadCompleted(
            userId: '123456789',
            processedCount: 76,
            skippedCount: 203,
            warningCount: 0,
            errorCount: 337,
        );

        $this->assertSame([
            'processedCount' => 76,
            'skippedCount' => 203,
            'warningCount' => 0,
            'errorCount' => 337,
        ], $event->broadcastWith());
    }

    #[Test]
    public function its_payload_stays_well_under_the_reverb_message_limit_regardless_of_error_volume(): void
    {
        // Reverb's max_message_size defaults to 10,000 bytes. A completion with
        // hundreds of row-level errors must not blow past it — the broadcast
        // carries counts only; full error detail goes to Discord.
        $event = new GrmUploadCompleted(
            userId: '123456789',
            processedCount: 76,
            skippedCount: 203,
            warningCount: 0,
            errorCount: 337,
        );

        $bytes = strlen((string) json_encode($event->broadcastWith()));

        $this->assertLessThan(10_000, $bytes);
    }
}
