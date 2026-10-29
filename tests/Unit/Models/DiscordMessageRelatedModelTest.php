<?php

namespace Tests\Unit\Models;

use App\Models\DiscordMessage;
use App\Models\DiscordMessageRelatedModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('discord-integration')]
class DiscordMessageRelatedModelTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return DiscordMessageRelatedModel::class;
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new DiscordMessageRelatedModel;

        $this->assertFillable($model, [
            'discord_message_id',
            'model_type',
            'model_id',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new DiscordMessageRelatedModel;

        $this->assertFillableAttribute($model, [
            'discord_message_id',
            'model_type',
            'model_id',
        ]);
    }

    // ==================== timestamps ====================

    #[Test]
    public function it_has_no_timestamps(): void
    {
        $attributes = (new \ReflectionClass(DiscordMessageRelatedModel::class))->getAttributes(WithoutTimestamps::class);

        $this->assertNotEmpty($attributes);
    }

    // ==================== persistence ====================

    #[Test]
    public function it_can_be_persisted_to_the_database(): void
    {
        $notification = DiscordMessage::factory()->create();
        $user = User::factory()->create();

        $related = DiscordMessageRelatedModel::create([
            'discord_message_id' => $notification->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        $this->assertModelExists($related);
        $this->assertTableHas([
            'discord_message_id' => $notification->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
    }

    // ==================== message ====================

    #[Test]
    public function message_returns_belongs_to_relationship(): void
    {
        $model = new DiscordMessageRelatedModel;

        $this->assertInstanceOf(BelongsTo::class, $model->message());
    }

    #[Test]
    public function message_returns_the_associated_discord_message(): void
    {
        $notification = DiscordMessage::factory()->create();
        $user = User::factory()->create();

        $related = DiscordMessageRelatedModel::create([
            'discord_message_id' => $notification->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        $this->assertTrue($related->fresh()->message->is($notification));
    }

    // ==================== relatedModel ====================

    #[Test]
    public function related_model_returns_morph_to_relationship(): void
    {
        $model = new DiscordMessageRelatedModel;

        $this->assertInstanceOf(MorphTo::class, $model->relatedModel());
    }

    #[Test]
    public function related_model_returns_the_associated_polymorphic_model(): void
    {
        $notification = DiscordMessage::factory()->create();
        $user = User::factory()->create();

        $related = DiscordMessageRelatedModel::create([
            'discord_message_id' => $notification->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        $this->assertTrue($related->fresh()->relatedModel->is($user));
    }
}
