<?php

namespace Tests\Unit\Notifications;

use App\Models\DailyQuest;
use App\Models\DiscordNotification;
use App\Models\DiscordRole;
use App\Models\User;
use App\Notifications\DailyQuestsMessage;
use App\Services\Discord\Notifications\Driver as DiscordDriver;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DailyQuestsMessageTest extends TestCase
{
    use RefreshDatabase;

    private NotifiableChannel $notifiable;

    protected function setUp(): void
    {
        parent::setUp();

        $channel = Channel::from(['id' => '123456789012345678', 'type' => 0]);
        $this->notifiable = new NotifiableChannel($channel);

        DiscordRole::factory()->create(['name' => 'Daily Quests Subscribers', 'id' => '999000111222333444']);
        config(['services.discord.roles.daily_quest_subscribers' => '999000111222333444']);
    }

    // -------------------------------------------------------------------------
    // via()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_routes_through_the_discord_driver(): void
    {
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null]);

        $this->assertSame(DiscordDriver::class, $notification->via($this->notifiable));
    }

    // -------------------------------------------------------------------------
    // toMessage() — content
    // -------------------------------------------------------------------------

    #[Test]
    public function it_mentions_the_daily_quests_subscribers_role_in_the_message_content(): void
    {
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null]);

        $payload = $notification->toMessage();

        $this->assertSame('<@&999000111222333444>', $payload->content);
    }

    // -------------------------------------------------------------------------
    // toMessage() — embed structure
    // -------------------------------------------------------------------------

    #[Test]
    public function it_builds_an_embed_with_the_correct_title(): void
    {
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null]);

        $embed = $notification->toMessage()->embeds[0];

        $this->assertSame('📜 Today\'s Daily Quests', $embed->title);
    }

    #[Test]
    public function it_builds_an_embed_with_a_description(): void
    {
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null]);

        $embed = $notification->toMessage()->embeds[0];

        $this->assertNotNull($embed->description);
    }

    #[Test]
    public function it_builds_an_embed_with_the_gold_color(): void
    {
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null]);

        $embed = $notification->toMessage()->embeds[0];

        $this->assertSame(15844367, $embed->color);
    }

    #[Test]
    public function it_builds_an_embed_with_a_timestamp(): void
    {
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null]);

        $embed = $notification->toMessage()->embeds[0];

        $this->assertNotNull($embed->timestamp);
    }

    #[Test]
    public function it_builds_an_embed_with_the_daily_quests_url(): void
    {
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null]);

        $embed = $notification->toMessage()->embeds[0];

        $this->assertSame(route('daily-quests.index'), $embed->url);
    }

    // -------------------------------------------------------------------------
    // toMessage() — embed fields
    // -------------------------------------------------------------------------

    #[Test]
    public function it_includes_a_field_for_each_non_null_quest(): void
    {
        $cooking = DailyQuest::factory()->cooking()->create();
        $fishing = DailyQuest::factory()->fishing()->create();

        $notification = new DailyQuestsMessage([
            'Cooking' => $cooking,
            'Fishing' => $fishing,
            'Dungeon' => null,
            'Heroic' => null,
            'PvP' => null,
        ]);

        $fields = $notification->toMessage()->embeds[0]->fields;

        $this->assertCount(2, $fields);
    }

    #[Test]
    public function it_skips_null_quest_entries(): void
    {
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null]);

        $fields = $notification->toMessage()->embeds[0]->fields;

        $this->assertEmpty($fields);
    }

    #[Test]
    public function it_uses_the_quest_name_as_the_embed_field_value(): void
    {
        $cooking = DailyQuest::factory()->cooking()->create(['name' => 'Delicious Surprise']);

        $notification = new DailyQuestsMessage([
            'Cooking' => $cooking,
            'Fishing' => null,
            'Dungeon' => null,
            'Heroic' => null,
            'PvP' => null,
        ]);

        $field = $notification->toMessage()->embeds[0]->fields[0];

        $this->assertSame('Delicious Surprise', $field->value);
    }

    // -------------------------------------------------------------------------
    // toMessage() — footer
    // -------------------------------------------------------------------------

    #[Test]
    public function it_omits_the_footer_when_no_sender_is_provided(): void
    {
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null]);

        $embed = $notification->toMessage()->embeds[0];

        $this->assertNull($embed->footer);
    }

    #[Test]
    public function it_includes_the_sender_nickname_in_the_footer(): void
    {
        $user = User::factory()->create(['nickname' => 'Arthas']);
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null], $user);

        $footer = $notification->toMessage()->embeds[0]->footer;

        $this->assertNotNull($footer);
        $this->assertStringContainsString('Arthas', $footer->text);
    }

    // -------------------------------------------------------------------------
    // relationships()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_empty_array_for_relationships_when_all_quests_are_null(): void
    {
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null]);

        $this->assertEmpty($notification->relationships());
    }

    #[Test]
    public function it_returns_only_non_null_quests_in_relationships_keyed_by_lowercase_type(): void
    {
        $cooking = DailyQuest::factory()->cooking()->create();
        $pvp = DailyQuest::factory()->pvp()->create();

        $notification = new DailyQuestsMessage([
            'Cooking' => $cooking,
            'Fishing' => null,
            'Dungeon' => null,
            'Heroic' => null,
            'PvP' => $pvp,
        ]);

        $relationships = $notification->relationships();

        $this->assertCount(2, $relationships);
        $this->assertArrayHasKey('cooking', $relationships);
        $this->assertArrayHasKey('pvp', $relationships);
        $this->assertTrue($relationships['cooking']->is($cooking));
        $this->assertTrue($relationships['pvp']->is($pvp));
    }

    // -------------------------------------------------------------------------
    // toDatabase()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_the_correct_database_payload(): void
    {
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null]);

        $data = $notification->toDatabase($this->notifiable);

        $this->assertSame(DailyQuestsMessage::class, $data['type']);
        $this->assertSame($this->notifiable->channel()->id, $data['channel_id']);
        $this->assertArrayHasKey('payload', $data);
        $this->assertArrayHasKey('related_models', $data);
        $this->assertNull($data['created_by_user_id']);
    }

    #[Test]
    public function it_includes_related_models_in_database_payload(): void
    {
        $cooking = DailyQuest::factory()->cooking()->create();

        $notification = new DailyQuestsMessage([
            'Cooking' => $cooking,
            'Fishing' => null,
            'Dungeon' => null,
            'Heroic' => null,
            'PvP' => null,
        ]);

        $data = $notification->toDatabase($this->notifiable);

        $this->assertCount(1, $data['related_models']);
        $this->assertSame('cooking', $data['related_models'][0]['name']);
        $this->assertSame(DailyQuest::class, $data['related_models'][0]['model']);
        $this->assertSame($cooking->id, $data['related_models'][0]['key']);
    }

    #[Test]
    public function it_includes_the_sender_id_in_the_database_payload(): void
    {
        $user = User::factory()->create();
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null], $user);

        $data = $notification->toDatabase($this->notifiable);

        $this->assertSame($user->id, $data['created_by_user_id']);
    }

    // -------------------------------------------------------------------------
    // updates() / sender()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_null_for_updates_when_none_provided(): void
    {
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null]);

        $this->assertNull($notification->updates());
    }

    #[Test]
    public function it_returns_the_discord_notification_instance_to_update(): void
    {
        $existing = DiscordNotification::factory()->create();
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null], null, $existing);

        $this->assertSame($existing->id, $notification->updates()->id);
    }

    #[Test]
    public function it_returns_null_for_sender_when_none_provided(): void
    {
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null]);

        $this->assertNull($notification->sender());
    }

    #[Test]
    public function it_returns_the_sender_user(): void
    {
        $user = User::factory()->create();
        $notification = new DailyQuestsMessage(['Cooking' => null, 'Fishing' => null, 'Dungeon' => null, 'Heroic' => null, 'PvP' => null], $user);

        $this->assertSame($user->id, $notification->sender()->id);
    }
}
