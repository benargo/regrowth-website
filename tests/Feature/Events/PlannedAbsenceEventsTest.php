<?php

namespace Tests\Feature\Events;

use App\Events\PlannedAbsenceCreated;
use App\Events\PlannedAbsenceDeleted;
use App\Events\PlannedAbsenceUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlannedAbsenceEventsTest extends TestCase
{
    #[Test]
    public function planned_absence_created_broadcasts_on_private_channel(): void
    {
        $event = new PlannedAbsenceCreated;
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    #[Test]
    public function planned_absence_updated_broadcasts_on_private_channel(): void
    {
        $event = new PlannedAbsenceUpdated;
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    #[Test]
    public function planned_absence_deleted_broadcasts_on_private_channel(): void
    {
        $event = new PlannedAbsenceDeleted;
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }
}
