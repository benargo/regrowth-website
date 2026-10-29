<?php

namespace Tests\Unit\Models;

use App\Casts\AsClassName;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Models\Comment;
use App\Models\DiscordChannel;
use App\Models\DiscordMessage;
use App\Models\Event;
use App\Models\User;
use App\Notifications\RaidAssignmentsPublished;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\Support\ModelTestCase;

#[Group('discord-integration')]
class DiscordMessageTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return DiscordMessage::class;
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new DiscordMessage;

        $this->assertFillable($model, [
            'type',
            'channel_id',
            'message_id',
            'payload',
            'created_by_user_id',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new DiscordMessage;

        $this->assertFillableAttribute($model, [
            'type',
            'channel_id',
            'message_id',
            'payload',
            'created_by_user_id',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new DiscordMessage;

        $this->assertCasts($model, [
            'type' => AsClassName::class,
            'payload' => MessagePayloadData::class,
        ]);
    }

    #[Test]
    public function it_has_expected_hidden_attributes(): void
    {
        $model = new DiscordMessage;

        $this->assertHidden($model, [
            'updated_at',
            'deleted_at',
        ]);
    }

    // ==================== persistence ====================

    #[Test]
    public function it_can_be_created_via_factory(): void
    {
        $notification = $this->create();

        $this->assertModelExists($notification);
        $this->assertTableHas(['id' => $notification->id]);
    }

    #[Test]
    public function it_enforces_unique_message_id(): void
    {
        $notification = $this->create();

        $this->assertUniqueConstraint(
            fn () => $this->create(['message_id' => $notification->message_id])
        );
    }

    // ==================== relationships ====================

    #[Test]
    public function channel_returns_the_stored_channel(): void
    {
        $channel = DiscordChannel::factory()->create();
        $message = $this->create(['channel_id' => $channel->id]);

        $this->assertTrue($message->channel->is($channel));
    }

    #[Test]
    public function channel_returns_a_soft_deleted_channel(): void
    {
        $channel = DiscordChannel::factory()->trashed()->create();
        $message = $this->create(['channel_id' => $channel->id]);

        $this->assertTrue($message->channel->is($channel));
    }

    #[Test]
    public function created_by_user_returns_belongs_to_relationship(): void
    {
        $model = new DiscordMessage;

        $this->assertInstanceOf(BelongsTo::class, $model->createdByUser());
    }

    #[Test]
    public function created_by_user_returns_the_user_who_created_the_notification(): void
    {
        $user = User::factory()->create();
        $notification = DiscordMessage::factory()->createdByUser($user)->create();

        $this->assertTrue($notification->createdByUser->is($user));
    }

    #[Test]
    public function created_by_user_returns_null_when_created_by_system(): void
    {
        $notification = $this->create(['created_by_user_id' => null]);

        $this->assertNull($notification->createdByUser);
    }

    #[Test]
    public function related_models_returns_morph_to_many_relationship(): void
    {
        $model = new DiscordMessage;

        $this->assertInstanceOf(HasMany::class, $model->relatedModels());
    }

    #[Test]
    public function related_models_returns_empty_collection_by_default(): void
    {
        $notification = $this->create();

        $this->assertCount(0, $notification->relatedModels()->get());
    }

    #[Test]
    public function related_models_returns_pivot_rows_keyed_to_the_notification(): void
    {
        $user = User::factory()->create();
        $notification = DiscordMessage::factory()
            ->withRelatedModels([['model_id' => $user->id, 'model_type' => User::class]])
            ->create();

        $rows = $notification->fresh()->relatedModels()->get();

        $this->assertCount(1, $rows);
        $this->assertSame((string) $user->id, $rows->first()->model_id);
        $this->assertSame(User::class, $rows->first()->model_type);
    }

    // ==================== syncRelatedModels ====================

    #[Test]
    public function sync_related_models_upserts_desired_rows_and_deletes_the_rest(): void
    {
        [$userA, $userB, $userC] = User::factory()->count(3)->create();
        $message = DiscordMessage::factory()
            ->withRelatedModels([
                ['model_id' => $userA->id, 'model_type' => User::class],
                ['model_id' => $userB->id, 'model_type' => User::class],
            ])
            ->create();

        $message->syncRelatedModels([
            ['model_id' => $userB->id, 'model_type' => User::class],
            ['model_id' => $userC->id, 'model_type' => User::class],
        ]);

        $this->assertEqualsCanonicalizing(
            [(string) $userB->id, (string) $userC->id],
            $message->relatedModels()->pluck('model_id')->all(),
        );
    }

    #[Test]
    public function sync_related_models_with_an_empty_list_deletes_all_rows(): void
    {
        $user = User::factory()->create();
        $message = DiscordMessage::factory()
            ->withRelatedModels([['model_id' => $user->id, 'model_type' => User::class]])
            ->create();

        $message->syncRelatedModels([]);

        $this->assertSame(0, $message->relatedModels()->count());
    }

    // ==================== soft deletes ====================

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        $model = new DiscordMessage;

        $this->assertContains(SoftDeletes::class, class_uses_recursive($model));
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

    // ==================== type and payload casts ====================

    #[Test]
    public function type_is_stored_and_retrieved_as_fully_qualified_class_name(): void
    {
        $notification = $this->create(['type' => RaidAssignmentsPublished::class]);

        $this->assertSame(RaidAssignmentsPublished::class, $notification->fresh()->type);
    }

    #[Test]
    public function payload_is_cast_to_message_payload(): void
    {
        $notification = $this->create([
            'payload' => MessagePayloadData::from(['content' => 'Hello, world!']),
        ]);

        $this->assertInstanceOf(MessagePayloadData::class, $notification->fresh()->payload);
        $this->assertSame('Hello, world!', $notification->fresh()->payload->content);
    }

    #[Test]
    #[Group('contract')]
    public function payload_cast_hydrates_a_legacy_snake_case_row(): void
    {
        $notification = DiscordMessage::factory()->create();

        DB::table('discord_messages')->where('id', $notification->id)->update([
            'payload' => json_encode([
                'content' => 'Hi',
                'embeds' => [[
                    'title' => 'Daily',
                    'footer' => null,
                    'image' => ['url' => 'https://regrowth.test/i.webp', 'content_type' => 'image/webp'],
                    'fields' => [['name' => 'Cooking', 'value' => 'Soup', 'inline' => false]],
                ]],
            ]),
        ]);

        $payload = $notification->fresh()->payload;

        $this->assertInstanceOf(MessagePayloadData::class, $payload);
        $this->assertInstanceOf(Optional::class, $payload->embeds[0]->footer);
        $this->assertSame('image/webp', $payload->embeds[0]->image->contentType);
        $this->assertSame('Soup', $payload->embeds[0]->fields[0]->value);
    }

    #[Test]
    #[Group('contract')]
    public function payload_cast_persists_snake_case_json(): void
    {
        $notification = DiscordMessage::factory()->create([
            'payload' => MessagePayloadData::from([
                'embeds' => [['title' => 't', 'footer' => ['text' => 'f', 'icon_url' => 'https://regrowth.test/a.png']]],
            ]),
        ]);

        $stored = json_decode(DB::table('discord_messages')->where('id', $notification->id)->value('payload'), true);

        $this->assertSame('https://regrowth.test/a.png', $stored['embeds'][0]['footer']['icon_url']);
        $this->assertArrayNotHasKey('iconUrl', $stored['embeds'][0]['footer']);
    }

    // ==================== relatedTo scope ====================

    #[Test]
    public function related_to_matches_notifications_linked_to_the_given_model(): void
    {
        $event = Event::factory()->create();
        $linked = DiscordMessage::factory()
            ->withRelatedModels([['model_type' => Event::class, 'model_id' => $event->getKey()]])
            ->create();
        DiscordMessage::factory()
            ->withRelatedModels([['model_type' => Event::class, 'model_id' => Event::factory()->create()->getKey()]])
            ->create();
        DiscordMessage::factory()->create();

        $matched = DiscordMessage::relatedTo(Event::class, $event->getKey())->pluck('id')->all();

        $this->assertSame([$linked->id], $matched);
    }

    #[Test]
    public function related_to_ignores_a_different_model_type_with_the_same_id(): void
    {
        $event = Event::factory()->create();
        DiscordMessage::factory()
            ->withRelatedModels([['model_type' => Comment::class, 'model_id' => $event->getKey()]])
            ->create();

        $matched = DiscordMessage::relatedTo(Event::class, $event->getKey())->exists();

        $this->assertFalse($matched);
    }
}
