<?php

namespace Tests\Unit\Notifications\Concerns;

use App\Models\DiscordNotification;
use App\Notifications\Concerns\UpdatesExisting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConcreteUpdatesExisting
{
    use UpdatesExisting;
}

class UpdatesExistingTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // updates()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_defaults_updates_to_null(): void
    {
        $notification = new ConcreteUpdatesExisting;

        $this->assertNull($notification->updates());
    }

    // -------------------------------------------------------------------------
    // updatesExisting()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_self_from_updates_existing(): void
    {
        $notification = new ConcreteUpdatesExisting;
        $discordNotification = DiscordNotification::factory()->create();

        $result = $notification->updatesExisting($discordNotification);

        $this->assertSame($notification, $result);
    }

    #[Test]
    public function it_stores_the_discord_notification_to_update_existing(): void
    {
        $notification = new ConcreteUpdatesExisting;
        $discordNotification = DiscordNotification::factory()->create();

        $notification->updatesExisting($discordNotification);

        $this->assertSame($discordNotification, $notification->updates());
    }

    #[Test]
    public function it_accepts_null_when_no_existing_notification_exists(): void
    {
        $notification = new ConcreteUpdatesExisting;

        $result = $notification->updatesExisting(null);

        $this->assertSame($notification, $result);
        $this->assertNull($notification->updates());
    }
}
