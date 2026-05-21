<?php

namespace Tests\Unit\Models;

use App\Casts\AsClassName;
use App\Models\DiscordNotification;
use App\Models\User;
use App\Notifications\DailyQuestsMessage;
use App\Services\Discord\Payloads\MessagePayload;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class DiscordNotificationTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return DiscordNotification::class;
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Fillable attributes
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new DiscordNotification;

        $this->assertFillable($model, [
            'type',
            'channel_id',
            'message_id',
            'payload',
            'created_by_user_id',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Casts
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new DiscordNotification;

        $this->assertCasts($model, [
            'type' => AsClassName::class,
            'payload' => MessagePayload::class,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Hidden attributes
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_has_expected_hidden_attributes(): void
    {
        $model = new DiscordNotification;

        $this->assertHidden($model, [
            'updated_at',
            'deleted_at',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Persistence
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Test: Relationships — createdByUser
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function created_by_user_returns_belongs_to_relationship(): void
    {
        $model = new DiscordNotification;

        $this->assertInstanceOf(BelongsTo::class, $model->createdByUser());
    }

    #[Test]
    public function created_by_user_returns_the_user_who_created_the_notification(): void
    {
        $user = User::factory()->create();
        $notification = DiscordNotification::factory()->createdByUser($user)->create();

        $this->assertTrue($notification->createdByUser->is($user));
    }

    #[Test]
    public function created_by_user_returns_null_when_created_by_system(): void
    {
        $notification = $this->create(['created_by_user_id' => null]);

        $this->assertNull($notification->createdByUser);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Relationships — relatedModels
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function related_models_returns_has_many_relationship(): void
    {
        $model = new DiscordNotification;

        $this->assertInstanceOf(HasMany::class, $model->relatedModels());
    }

    #[Test]
    public function related_models_returns_empty_collection_by_default(): void
    {
        $notification = $this->create();

        $this->assertCount(0, $notification->relatedModels);
    }

    #[Test]
    public function related_models_returns_pivot_rows_keyed_to_the_notification(): void
    {
        $user = User::factory()->create();
        $notification = DiscordNotification::factory()
            ->withRelatedModels([User::class => [$user->id]])
            ->create();

        $rows = $notification->fresh()->relatedModels;

        $this->assertCount(1, $rows);
        $this->assertSame(User::class, $rows->first()->model_type);
        $this->assertSame((string) $user->id, $rows->first()->model_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Soft deletes
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        $model = new DiscordNotification;

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

    /*
    |--------------------------------------------------------------------------
    | Test: Type cast
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function type_is_stored_and_retrieved_as_fully_qualified_class_name(): void
    {
        $notification = $this->create(['type' => DailyQuestsMessage::class]);

        $this->assertSame(DailyQuestsMessage::class, $notification->fresh()->type);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Payload cast
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function payload_is_cast_to_message_payload(): void
    {
        $notification = $this->create([
            'payload' => MessagePayload::from(['content' => 'Hello, world!']),
        ]);

        $this->assertInstanceOf(MessagePayload::class, $notification->fresh()->payload);
        $this->assertSame('Hello, world!', $notification->fresh()->payload->content);
    }
}
