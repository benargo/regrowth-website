<?php

namespace Tests\Unit\Models\TBC;

use App\Events\DailyQuestNotificationCreated;
use App\Events\DailyQuestNotificationDeleting;
use App\Events\DailyQuestNotificationUpdated;
use App\Models\TBC\DailyQuest;
use App\Models\TBC\DailyQuestNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class DailyQuestNotificationTest extends ModelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    protected function modelClass(): string
    {
        return DailyQuestNotification::class;
    }

    #[Test]
    public function it_uses_tbc_daily_quest_notifications_table(): void
    {
        $model = new DailyQuestNotification;

        $this->assertSame('tbc_daily_quest_notifications', $model->getTable());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new DailyQuestNotification;

        $this->assertFillable($model, [
            'date',
            'discord_message_id',
            'cooking_quest_id',
            'fishing_quest_id',
            'dungeon_quest_id',
            'heroic_quest_id',
            'pvp_quest_id',
            'sent_by_user_id',
            'updated_by_user_id',
        ]);
    }

    #[Test]
    public function it_casts_date_as_datetime(): void
    {
        $model = new DailyQuestNotification;

        $this->assertCasts($model, [
            'date' => 'datetime:Y-m-d H:i:s',
        ]);
    }

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        $model = new DailyQuestNotification;

        $this->assertContains(SoftDeletes::class, class_uses_recursive($model));
    }

    #[Test]
    public function it_dispatches_events(): void
    {
        $model = new DailyQuestNotification;

        $this->assertSame([
            'created' => DailyQuestNotificationCreated::class,
            'updated' => DailyQuestNotificationUpdated::class,
            'deleting' => DailyQuestNotificationDeleting::class,
        ], $model->dispatchesEvents());
    }

    #[Test]
    public function it_belongs_to_cooking_quest(): void
    {
        $quest = DailyQuest::factory()->cooking()->create();
        $notification = $this->create(['cooking_quest_id' => $quest->id]);

        $this->assertRelation($notification, 'cookingQuest', BelongsTo::class);
        $this->assertTrue($notification->cookingQuest->is($quest));
    }

    #[Test]
    public function it_belongs_to_fishing_quest(): void
    {
        $quest = DailyQuest::factory()->fishing()->create();
        $notification = $this->create(['fishing_quest_id' => $quest->id]);

        $this->assertRelation($notification, 'fishingQuest', BelongsTo::class);
        $this->assertTrue($notification->fishingQuest->is($quest));
    }

    #[Test]
    public function it_belongs_to_dungeon_quest(): void
    {
        $quest = DailyQuest::factory()->dungeon()->create();
        $notification = $this->create(['dungeon_quest_id' => $quest->id]);

        $this->assertRelation($notification, 'dungeonQuest', BelongsTo::class);
        $this->assertTrue($notification->dungeonQuest->is($quest));
    }

    #[Test]
    public function it_belongs_to_heroic_quest(): void
    {
        $quest = DailyQuest::factory()->heroic()->create();
        $notification = $this->create(['heroic_quest_id' => $quest->id]);

        $this->assertRelation($notification, 'heroicQuest', BelongsTo::class);
        $this->assertTrue($notification->heroicQuest->is($quest));
    }

    #[Test]
    public function it_belongs_to_pvp_quest(): void
    {
        $quest = DailyQuest::factory()->pvp()->create();
        $notification = $this->create(['pvp_quest_id' => $quest->id]);

        $this->assertRelation($notification, 'pvpQuest', BelongsTo::class);
        $this->assertTrue($notification->pvpQuest->is($quest));
    }

    #[Test]
    public function it_belongs_to_sent_by_user(): void
    {
        $user = User::factory()->create();
        $notification = $this->create(['sent_by_user_id' => $user->id]);

        $this->assertRelation($notification, 'sentBy', BelongsTo::class);
        $this->assertTrue($notification->sentBy->is($user));
    }

    #[Test]
    public function it_belongs_to_updated_by_user(): void
    {
        $user = User::factory()->create();
        $notification = $this->create(['updated_by_user_id' => $user->id]);

        $this->assertRelation($notification, 'updatedBy', BelongsTo::class);
        $this->assertTrue($notification->updatedBy->is($user));
    }

    #[Test]
    public function current_daily_quest_date_returns_today_3am_when_after_3am(): void
    {
        Carbon::setTestNow('2026-02-15 10:00:00', 'Europe/Paris');

        $date = DailyQuestNotification::currentDailyQuestDate();

        $this->assertEquals('2026-02-15 03:00:00', $date->format('Y-m-d H:i:s'));
        $this->assertEquals('Europe/Paris', $date->timezone->getName());
    }

    #[Test]
    public function current_daily_quest_date_returns_yesterday_3am_when_before_3am(): void
    {
        Carbon::setTestNow('2026-02-15 02:30:00', 'Europe/Paris');

        $date = DailyQuestNotification::currentDailyQuestDate();

        $this->assertEquals('2026-02-14 03:00:00', $date->format('Y-m-d H:i:s'));
        $this->assertEquals('Europe/Paris', $date->timezone->getName());
    }

    #[Test]
    public function current_daily_quest_date_returns_yesterday_3am_at_exactly_midnight(): void
    {
        Carbon::setTestNow('2026-02-15 00:00:00', 'Europe/Paris');

        $date = DailyQuestNotification::currentDailyQuestDate();

        $this->assertEquals('2026-02-14 03:00:00', $date->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function current_daily_quest_date_returns_today_3am_at_exactly_3am(): void
    {
        Carbon::setTestNow('2026-02-15 03:00:00', 'Europe/Paris');

        $date = DailyQuestNotification::currentDailyQuestDate();

        $this->assertEquals('2026-02-15 03:00:00', $date->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function current_daily_quest_date_handles_dst_transition_spring(): void
    {
        Carbon::setTestNow('2026-03-29 04:00:00', 'Europe/Paris');

        $date = DailyQuestNotification::currentDailyQuestDate();

        $this->assertEquals('2026-03-29 03:00:00', $date->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function current_daily_quest_date_handles_dst_transition_winter(): void
    {
        Carbon::setTestNow('2026-10-25 04:00:00', 'Europe/Paris');

        $date = DailyQuestNotification::currentDailyQuestDate();

        $this->assertEquals('2026-10-25 03:00:00', $date->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function exists_for_today_returns_true_when_notification_exists(): void
    {
        Carbon::setTestNow('2026-02-15 10:00:00', 'Europe/Paris');

        $this->create([
            'date' => DailyQuestNotification::currentDailyQuestDate(),
        ]);

        $this->assertTrue(DailyQuestNotification::existsForToday());
    }

    #[Test]
    public function exists_for_today_returns_false_when_no_notification(): void
    {
        Carbon::setTestNow('2026-02-15 10:00:00', 'Europe/Paris');

        $this->assertFalse(DailyQuestNotification::existsForToday());
    }

    #[Test]
    public function exists_for_today_returns_false_when_only_old_notification_exists(): void
    {
        Carbon::setTestNow('2026-02-15 10:00:00', 'Europe/Paris');

        $this->create([
            'date' => Carbon::parse('2026-02-14 03:00:00', 'Europe/Paris'),
        ]);

        $this->assertFalse(DailyQuestNotification::existsForToday());
    }

    #[Test]
    public function get_todays_notification_returns_notification_when_exists(): void
    {
        Carbon::setTestNow('2026-02-15 10:00:00', 'Europe/Paris');

        $created = $this->create([
            'date' => DailyQuestNotification::currentDailyQuestDate(),
        ]);

        $notification = DailyQuestNotification::getTodaysNotification();

        $this->assertNotNull($notification);
        $this->assertEquals($created->id, $notification->id);
    }

    #[Test]
    public function get_todays_notification_returns_null_when_none_exists(): void
    {
        Carbon::setTestNow('2026-02-15 10:00:00', 'Europe/Paris');

        $notification = DailyQuestNotification::getTodaysNotification();

        $this->assertNull($notification);
    }

    #[Test]
    public function get_todays_notification_returns_null_when_only_old_exists(): void
    {
        Carbon::setTestNow('2026-02-15 10:00:00', 'Europe/Paris');

        $this->create([
            'date' => Carbon::parse('2026-02-14 03:00:00', 'Europe/Paris'),
        ]);

        $notification = DailyQuestNotification::getTodaysNotification();

        $this->assertNull($notification);
    }

    #[Test]
    public function get_quests_returns_all_quest_relationships(): void
    {
        $notification = $this->create();

        $quests = $notification->getQuests();

        $this->assertCount(5, $quests);
        $this->assertInstanceOf(DailyQuest::class, $quests[0]);
    }

    #[Test]
    public function get_quests_filters_out_null_quests(): void
    {
        $notification = $this->create([
            'cooking_quest_id' => DailyQuest::factory()->cooking(),
            'fishing_quest_id' => null,
            'dungeon_quest_id' => null,
            'heroic_quest_id' => null,
            'pvp_quest_id' => DailyQuest::factory()->pvp(),
        ]);

        $quests = $notification->getQuests();

        $this->assertCount(2, $quests);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $notification = $this->create();

        $this->assertNotNull($notification->date);
        $this->assertNotNull($notification->discord_message_id);
        $this->assertNotNull($notification->cooking_quest_id);
        $this->assertNotNull($notification->fishing_quest_id);
        $this->assertNotNull($notification->dungeon_quest_id);
        $this->assertNotNull($notification->heroic_quest_id);
        $this->assertNotNull($notification->pvp_quest_id);
        $this->assertNotNull($notification->sent_by_user_id);
        $this->assertModelExists($notification);
    }

    #[Test]
    public function factory_without_discord_message_state_sets_message_id_to_null(): void
    {
        $notification = $this->factory()->withoutDiscordMessage()->create();

        $this->assertNull($notification->discord_message_id);
    }

    #[Test]
    public function factory_for_date_state_sets_specific_date(): void
    {
        $specificDate = Carbon::parse('2026-01-10 03:00:00', 'Europe/Paris');

        $notification = $this->factory()->forDate($specificDate)->create();

        $this->assertEquals($specificDate->format('Y-m-d H:i:s'), $notification->date->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function factory_updated_state_adds_updated_by_user(): void
    {
        $notification = $this->factory()->updated()->create();

        $this->assertNotNull($notification->updated_by_user_id);
        $this->assertInstanceOf(User::class, $notification->updatedBy);
    }

    #[Test]
    public function it_can_be_soft_deleted(): void
    {
        $notification = $this->create();

        $notification->delete();

        $this->assertSoftDeleted($notification);
    }

    #[Test]
    public function it_can_be_restored_after_soft_delete(): void
    {
        $notification = $this->create();
        $notification->delete();

        $notification->restore();

        $this->assertModelExists($notification);
        $this->assertNull($notification->deleted_at);
    }
}
