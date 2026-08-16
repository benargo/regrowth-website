<?php

namespace Tests\Unit\Services\Discord\Notifications;

use App\Models\User;
use App\Services\Discord\Notifications\Driver;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Notifications\Notification;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Channel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class StubModelA extends Model
{
    protected $table = 'stub_a';
}

class StubModelB extends Model
{
    protected $table = 'stub_b';

    protected $keyType = 'string';

    public $incrementing = false;
}

class ConcreteNotification extends Notification
{
    public function toMessage(): MessagePayload
    {
        return MessagePayload::from([]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [];
    }

    /**
     * Expose the protected hydrate() helper for testing.
     *
     * @param  array{model_id: int|string, model_type: class-string<Model>}|null  $reference
     */
    public function hydratePublic(?array $reference): ?Model
    {
        return $this->hydrate($reference);
    }

    /**
     * Expose the protected hydrateOrFail() helper for testing.
     *
     * @param  array{model_id: int|string, model_type: class-string<Model>}|null  $reference
     */
    public function hydrateOrFailPublic(?array $reference): Model
    {
        return $this->hydrateOrFail($reference);
    }

    /**
     * Expose the protected relatedModel() helper for testing.
     *
     * @return array{model_id: int|string, model_type: class-string<Model>}|null
     */
    public function relatedModelPublic(string $type): ?array
    {
        return $this->relatedModel($type);
    }
}

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private NotifiableChannel $notifiable;

    protected function setUp(): void
    {
        parent::setUp();

        $channel = Channel::from(['id' => '123456789012345678', 'type' => 0]);
        $this->notifiable = new NotifiableChannel($channel);
    }

    #[Test]
    public function it_routes_through_the_discord_driver(): void
    {
        $notification = new ConcreteNotification;

        $this->assertContains(Driver::class, $notification->via($this->notifiable));
    }

    // ==================== withRelatedModels ====================

    #[Test]
    public function it_returns_self_from_with_related_models(): void
    {
        $notification = new ConcreteNotification;

        $result = $notification->withRelatedModels([]);

        $this->assertSame($notification, $result);
    }

    #[Test]
    public function it_accepts_an_iterable_of_models(): void
    {
        $notification = new ConcreteNotification;
        $model = $this->createStub(Model::class);

        $result = $notification->withRelatedModels(['model' => $model]);

        $this->assertSame($notification, $result);
    }

    // ==================== mapRelatedModels ====================

    #[Test]
    public function it_returns_empty_array_when_no_related_models_set(): void
    {
        $notification = new ConcreteNotification;

        $this->assertSame([], $notification->mapRelatedModels());
    }

    #[Test]
    public function it_returns_empty_array_when_related_models_set_to_empty(): void
    {
        $notification = new ConcreteNotification;
        $notification->withRelatedModels([]);

        $this->assertSame([], $notification->mapRelatedModels());
    }

    #[Test]
    public function it_maps_models_with_integer_primary_keys_by_fqcn(): void
    {
        $modelA = $this->createStub(Model::class);
        $modelA->method('getKey')->willReturn(1);

        $modelB = $this->createStub(Model::class);
        $modelB->method('getKey')->willReturn(2);

        $notification = new ConcreteNotification;
        $notification->withRelatedModels([$modelA, $modelB]);

        $result = $notification->mapRelatedModels();

        $this->assertSame([
            ['model_id' => 1, 'model_type' => get_class($modelA)],
            ['model_id' => 2, 'model_type' => get_class($modelB)],
        ], $result);
    }

    #[Test]
    public function it_maps_models_with_uuid_primary_keys_by_fqcn(): void
    {
        $modelA = $this->createStub(Model::class);
        $modelA->method('getKey')->willReturn('550e8400-e29b-41d4-a716-446655440000');

        $modelB = $this->createStub(Model::class);
        $modelB->method('getKey')->willReturn('6ba7b810-9dad-11d1-80b4-00c04fd430c8');

        $notification = new ConcreteNotification;
        $notification->withRelatedModels([$modelA, $modelB]);

        $result = $notification->mapRelatedModels();

        $this->assertSame([
            ['model_id' => '550e8400-e29b-41d4-a716-446655440000', 'model_type' => get_class($modelA)],
            ['model_id' => '6ba7b810-9dad-11d1-80b4-00c04fd430c8', 'model_type' => get_class($modelB)],
        ], $result);
    }

    #[Test]
    public function it_groups_models_of_different_classes_under_separate_fqcn_keys(): void
    {
        $modelA = new StubModelA;
        $modelA->id = 1;

        $modelB = new StubModelB;
        $modelB->id = 'abc-uuid';

        $notification = new ConcreteNotification;
        $notification->withRelatedModels([$modelA, $modelB]);

        $result = $notification->mapRelatedModels();

        $this->assertSame([
            ['model_id' => 1, 'model_type' => StubModelA::class],
            ['model_id' => 'abc-uuid', 'model_type' => StubModelB::class],
        ], $result);
    }

    #[Test]
    public function it_groups_multiple_models_of_the_same_class_together(): void
    {
        $modelA = new StubModelA;
        $modelA->id = 10;

        $modelB = new StubModelA;
        $modelB->id = 20;

        $modelC = new StubModelA;
        $modelC->id = 30;

        $notification = new ConcreteNotification;
        $notification->withRelatedModels([$modelA, $modelB, $modelC]);

        $result = $notification->mapRelatedModels();

        $this->assertSame([
            ['model_id' => 10, 'model_type' => StubModelA::class],
            ['model_id' => 20, 'model_type' => StubModelA::class],
            ['model_id' => 30, 'model_type' => StubModelA::class],
        ], $result);
    }

    // ==================== hydrate ====================

    #[Test]
    public function it_hydrates_a_model_from_a_reference(): void
    {
        $user = User::factory()->create();
        $notification = new ConcreteNotification;

        $model = $notification->hydratePublic([
            'model_id' => $user->getKey(),
            'model_type' => User::class,
        ]);

        $this->assertInstanceOf(User::class, $model);
        $this->assertTrue($model->is($user));
    }

    #[Test]
    public function it_returns_null_when_hydrating_a_null_reference(): void
    {
        $notification = new ConcreteNotification;

        $this->assertNull($notification->hydratePublic(null));
    }

    #[Test]
    public function it_returns_null_when_hydrating_a_reference_to_a_missing_model(): void
    {
        $notification = new ConcreteNotification;

        $model = $notification->hydratePublic([
            'model_id' => 999999,
            'model_type' => User::class,
        ]);

        $this->assertNull($model);
    }

    // ==================== hydrateOrFail ====================

    #[Test]
    public function it_hydrates_or_fail_returns_the_model_when_present(): void
    {
        $user = User::factory()->create();
        $notification = new ConcreteNotification;

        $model = $notification->hydrateOrFailPublic([
            'model_id' => $user->getKey(),
            'model_type' => User::class,
        ]);

        $this->assertTrue($model->is($user));
    }

    #[Group('error-handling')]
    #[Test]
    public function it_hydrates_or_fail_throws_when_the_model_is_missing(): void
    {
        $notification = new ConcreteNotification;

        $this->expectException(ModelNotFoundException::class);

        $notification->hydrateOrFailPublic([
            'model_id' => 999999,
            'model_type' => User::class,
        ]);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_hydrates_or_fail_throws_when_the_reference_is_null(): void
    {
        $notification = new ConcreteNotification;

        $this->expectException(ModelNotFoundException::class);

        $notification->hydrateOrFailPublic(null);
    }

    // ==================== relatedModel ====================

    #[Test]
    public function it_returns_the_first_related_reference_for_a_given_type(): void
    {
        $modelA = new StubModelA;
        $modelA->id = 1;

        $modelB = new StubModelB;
        $modelB->id = 'abc-uuid';

        $notification = new ConcreteNotification;
        $notification->withRelatedModels([$modelA, $modelB]);

        $this->assertSame(
            ['model_id' => 1, 'model_type' => StubModelA::class],
            $notification->relatedModelPublic(StubModelA::class),
        );
        $this->assertSame(
            ['model_id' => 'abc-uuid', 'model_type' => StubModelB::class],
            $notification->relatedModelPublic(StubModelB::class),
        );
    }

    #[Test]
    public function it_returns_null_when_no_related_reference_matches_the_type(): void
    {
        $notification = new ConcreteNotification;
        $notification->withRelatedModels([]);

        $this->assertNull($notification->relatedModelPublic(StubModelA::class));
    }

    // ==================== withSender ====================

    #[Test]
    public function it_returns_self_from_with_sender(): void
    {
        $notification = new ConcreteNotification;
        $user = $this->createStub(Authenticatable::class);

        $result = $notification->withSender($user);

        $this->assertSame($notification, $result);
    }

    // ==================== sender ====================

    #[Test]
    public function it_returns_null_sender_by_default(): void
    {
        $notification = new ConcreteNotification;

        $this->assertNull($notification->sender());
    }

    #[Test]
    public function it_returns_the_sender_after_with_sender_is_called(): void
    {
        $notification = new ConcreteNotification;
        $user = $this->createStub(Authenticatable::class);
        $notification->withSender($user);

        $this->assertSame($user, $notification->sender());
    }

    // ==================== shouldQueue contract ====================

    #[Test]
    public function it_implements_should_queue(): void
    {
        $notification = new ConcreteNotification;

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }
}
