<?php

namespace Tests\Feature\Providers;

use App\Listeners\BroadcastGrmUploadRetry;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_the_grm_upload_retry_listener_for_job_released_after_exception(): void
    {
        $listeners = Event::getRawListeners()[JobReleasedAfterException::class] ?? [];

        $this->assertContains(BroadcastGrmUploadRetry::class, $listeners);
    }
}
