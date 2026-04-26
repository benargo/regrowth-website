<?php

namespace Tests\Unit\Casts;

use App\Casts\AsNotificationType;
use App\Notifications\DailyQuestsMessage;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

class AsNotificationTypeTest extends TestCase
{
    #[Test]
    public function get_returns_the_value_as_is(): void
    {
        $cast = new AsNotificationType;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'notification_type', DailyQuestsMessage::class, []);

        $this->assertSame(DailyQuestsMessage::class, $result);
    }

    #[Test]
    public function get_returns_null_when_value_is_null(): void
    {
        $cast = new AsNotificationType;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'notification_type', null, []);

        $this->assertNull($result);
    }

    #[Test]
    public function set_returns_a_valid_class_name_string(): void
    {
        $cast = new AsNotificationType;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'notification_type', DailyQuestsMessage::class, []);

        $this->assertSame(DailyQuestsMessage::class, $result);
    }

    #[Test]
    public function set_returns_null_when_value_is_null(): void
    {
        $cast = new AsNotificationType;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'notification_type', null, []);

        $this->assertNull($result);
    }

    #[Test]
    public function set_throws_when_value_is_not_a_string(): void
    {
        $cast = new AsNotificationType;
        $model = $this->createStub(Model::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification type must be a fully qualified class name string.');

        $cast->set($model, 'notification_type', new stdClass, []);
    }

    #[Test]
    public function set_throws_when_class_does_not_exist(): void
    {
        $cast = new AsNotificationType;
        $model = $this->createStub(Model::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification type class [App\Notifications\NonExistentNotification] does not exist.');

        $cast->set($model, 'notification_type', 'App\Notifications\NonExistentNotification', []);
    }
}
