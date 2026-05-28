<?php

namespace Tests\Unit\Models;

use App\Models\DiscordNotification;
use App\Models\DiscordNotificationRelatedModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class DiscordNotificationRelatedModelTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return DiscordNotificationRelatedModel::class;
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Fillable attributes
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new DiscordNotificationRelatedModel;

        $this->assertFillable($model, [
            'discord_notification_id',
            'model_type',
            'model_id',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Timestamps
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_has_no_timestamps(): void
    {
        $attributes = (new \ReflectionClass(DiscordNotificationRelatedModel::class))->getAttributes(WithoutTimestamps::class);

        $this->assertNotEmpty($attributes);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Persistence
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function it_can_be_persisted_to_the_database(): void
    {
        $notification = DiscordNotification::factory()->create();
        $user = User::factory()->create();

        $related = DiscordNotificationRelatedModel::create([
            'discord_notification_id' => $notification->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        $this->assertModelExists($related);
        $this->assertTableHas([
            'discord_notification_id' => $notification->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Relationships — notification
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function notification_returns_belongs_to_relationship(): void
    {
        $model = new DiscordNotificationRelatedModel;

        $this->assertInstanceOf(BelongsTo::class, $model->notification());
    }

    #[Test]
    public function notification_returns_the_associated_discord_notification(): void
    {
        $notification = DiscordNotification::factory()->create();
        $user = User::factory()->create();

        $related = DiscordNotificationRelatedModel::create([
            'discord_notification_id' => $notification->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        $this->assertTrue($related->fresh()->notification->is($notification));
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Relationships — relatedModel
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function related_model_returns_morph_to_relationship(): void
    {
        $model = new DiscordNotificationRelatedModel;

        $this->assertInstanceOf(MorphTo::class, $model->relatedModel());
    }

    #[Test]
    public function related_model_returns_the_associated_polymorphic_model(): void
    {
        $notification = DiscordNotification::factory()->create();
        $user = User::factory()->create();

        $related = DiscordNotificationRelatedModel::create([
            'discord_notification_id' => $notification->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        $this->assertTrue($related->fresh()->relatedModel->is($user));
    }
}
