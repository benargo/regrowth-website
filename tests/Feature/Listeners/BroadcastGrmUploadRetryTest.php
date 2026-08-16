<?php

namespace Tests\Feature\Listeners;

use App\Events\Broadcasts\GrmUploadRetrying;
use App\Jobs\ProcessGrmUpload;
use App\Listeners\BroadcastGrmUploadRetry;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
#[Group('broadcasting')]
class BroadcastGrmUploadRetryTest extends TestCase
{
    #[Test]
    public function it_broadcasts_a_retry_event_for_a_released_grm_upload_job(): void
    {
        Event::fake([GrmUploadRetrying::class]);

        $command = new ProcessGrmUpload($this->grmData(), 'user-123');

        $job = $this->mock(Job::class, function (MockInterface $mock) use ($command) {
            $mock->shouldReceive('resolveName')->andReturn(ProcessGrmUpload::class);
            $mock->shouldReceive('attempts')->andReturn(1);
            $mock->shouldReceive('maxTries')->andReturn(3);
            $mock->shouldReceive('payload')->andReturn([
                'data' => ['command' => serialize($command)],
            ]);
        });

        $event = new JobReleasedAfterException('redis', $job, backoff: 60);

        (new BroadcastGrmUploadRetry)->handle($event);

        Event::assertDispatched(GrmUploadRetrying::class, function (GrmUploadRetrying $event) {
            return $event->userId === 'user-123'
                && $event->attempt === 1
                && $event->maxTries === 3
                && $event->retryAfter === 60;
        });
    }

    #[Test]
    public function it_ignores_released_jobs_that_are_not_grm_uploads(): void
    {
        Event::fake([GrmUploadRetrying::class]);

        $job = $this->mock(Job::class, function (MockInterface $mock) {
            $mock->shouldReceive('resolveName')->andReturn('App\\Jobs\\SomeOtherJob');
        });

        $event = new JobReleasedAfterException('redis', $job, backoff: 60);

        (new BroadcastGrmUploadRetry)->handle($event);

        Event::assertNotDispatched(GrmUploadRetrying::class);
    }

    /**
     * @return array{delimiter: string, headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    private function grmData(): array
    {
        return [
            'delimiter' => ',',
            'headers' => ['Name', 'Rank', 'Level', 'Last Online (Days)', 'Main/Alt', 'Player Alts'],
            'rows' => [
                ['Name' => 'TestChar', 'Rank' => 'Raider', 'Level' => '80', 'Last Online (Days)' => '1', 'Main/Alt' => 'Main', 'Player Alts' => ''],
            ],
        ];
    }
}
