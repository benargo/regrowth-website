<?php

namespace Tests\Feature\Notifications\Concerns;

use App\Http\Integrations\Discord\Data\Channels\ChannelStub;
use App\Models\DiscordNotification;
use App\Notifications\Concerns\UpdatesExisting;
use App\Notifications\Contracts\ShouldUpdateExisting;
use App\Notifications\DailyQuestsMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdatesExistingFixture implements ShouldUpdateExisting
{
    use UpdatesExisting;

    public function __construct(private readonly ?string $onlyMessageId = null) {}

    protected function constrainExisting(Builder $query): Builder
    {
        if ($this->onlyMessageId === null) {
            return $query;
        }

        return $query->where('message_id', $this->onlyMessageId);
    }
}

#[Group('discord-integration')]
class UpdatesExistingTest extends TestCase
{
    use RefreshDatabase;

    private const string CHANNEL_ID = '987654321098765432';

    #[Test]
    public function it_returns_the_latest_notification_of_its_type_in_the_channel(): void
    {
        $this->travelTo('2026-10-14 12:00:00');
        $this->storedNotification('111111111111111111');
        $this->travelTo('2026-10-14 12:05:00');
        $latest = $this->storedNotification('222222222222222222');

        $found = (new UpdatesExistingFixture)->existingNotification(new ChannelStub(self::CHANNEL_ID));

        $this->assertTrue($found->is($latest));
    }

    #[Test]
    public function it_ignores_notifications_of_another_type(): void
    {
        $this->storedNotification('111111111111111111', type: DailyQuestsMessage::class);

        $found = (new UpdatesExistingFixture)->existingNotification(new ChannelStub(self::CHANNEL_ID));

        $this->assertNull($found);
    }

    #[Test]
    public function it_ignores_notifications_posted_in_another_channel(): void
    {
        $this->storedNotification('111111111111111111', channelId: '555555555555555555');

        $found = (new UpdatesExistingFixture)->existingNotification(new ChannelStub(self::CHANNEL_ID));

        $this->assertNull($found);
    }

    #[Test]
    public function it_ignores_soft_deleted_notifications(): void
    {
        $this->storedNotification('111111111111111111')->delete();

        $found = (new UpdatesExistingFixture)->existingNotification(new ChannelStub(self::CHANNEL_ID));

        $this->assertNull($found);
    }

    #[Test]
    public function it_narrows_the_lookup_with_the_class_constraint(): void
    {
        $this->travelTo('2026-10-14 12:00:00');
        $wanted = $this->storedNotification('111111111111111111');
        $this->travelTo('2026-10-14 12:05:00');
        $this->storedNotification('222222222222222222');

        $found = (new UpdatesExistingFixture('111111111111111111'))->existingNotification(new ChannelStub(self::CHANNEL_ID));

        $this->assertTrue($found->is($wanted));
    }

    #[Test]
    public function it_looks_up_the_notification_only_once_even_when_none_exists(): void
    {
        $notification = new UpdatesExistingFixture;
        $channel = new ChannelStub(self::CHANNEL_ID);

        $this->expectsDatabaseQueryCount(1);

        $notification->existingNotification($channel);
        $notification->existingNotification($channel);
    }

    // ==================== helpers ====================

    private function storedNotification(
        string $messageId,
        string $type = UpdatesExistingFixture::class,
        string $channelId = self::CHANNEL_ID,
    ): DiscordNotification {
        return DiscordNotification::factory()->create([
            'type' => $type,
            'channel_id' => $channelId,
            'message_id' => $messageId,
        ]);
    }
}
