<?php

namespace Tests\Feature\Events;

use App\Events\CommentCreated;
use App\Events\CommentDeleted;
use App\Events\CommentUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommentEventsTest extends TestCase
{
    #[Test]
    public function comment_created_broadcasts_on_private_channel(): void
    {
        $event = new CommentCreated;
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    #[Test]
    public function comment_updated_broadcasts_on_private_channel(): void
    {
        $event = new CommentUpdated;
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    #[Test]
    public function comment_deleted_broadcasts_on_private_channel(): void
    {
        $event = new CommentDeleted;
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }
}
