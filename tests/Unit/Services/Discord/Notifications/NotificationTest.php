<?php

namespace Tests\Unit\Services\Discord\Notifications;

use App\Services\Discord\Notifications\Driver;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Notifications\Notification;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Channel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
}

class NotificationTest extends TestCase
{
    private NotifiableChannel $notifiable;

    protected function setUp(): void
    {
        parent::setUp();

        $channel = Channel::from(['id' => '123456789012345678', 'type' => 0]);
        $this->notifiable = new NotifiableChannel($channel);
    }

    // -------------------------------------------------------------------------
    // via()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_routes_through_the_discord_driver(): void
    {
        $notification = new ConcreteNotification;

        $this->assertSame(Driver::class, $notification->via($this->notifiable));
    }

    // -------------------------------------------------------------------------
    // withRelatedModels()
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // mapRelatedModels()
    // -------------------------------------------------------------------------

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

        $this->assertSame([get_class($modelA) => [1, 2]], $result);
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
            get_class($modelA) => [
                '550e8400-e29b-41d4-a716-446655440000',
                '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            ],
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

        $this->assertArrayHasKey(StubModelA::class, $result);
        $this->assertArrayHasKey(StubModelB::class, $result);
        $this->assertSame([1], $result[StubModelA::class]);
        $this->assertSame(['abc-uuid'], $result[StubModelB::class]);
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

        $this->assertSame([StubModelA::class => [10, 20, 30]], $result);
    }

    // -------------------------------------------------------------------------
    // withSender()
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_self_from_with_sender(): void
    {
        $notification = new ConcreteNotification;
        $user = $this->createStub(Authenticatable::class);

        $result = $notification->withSender($user);

        $this->assertSame($notification, $result);
    }

    // -------------------------------------------------------------------------
    // sender()
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // ShouldQueue / Queueable
    // -------------------------------------------------------------------------

    #[Test]
    public function it_implements_should_queue(): void
    {
        $notification = new ConcreteNotification;

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }
}
