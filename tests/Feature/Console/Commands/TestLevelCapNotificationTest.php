<?php

namespace Tests\Feature\Console\Commands;

use App\Notifications\DiscordNotifiable;
use App\Notifications\LevelCapAchieved;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TestLevelCapNotificationTest extends TestCase
{
    public function test_it_sends_notification_with_single_name(): void
    {
        Notification::fake();

        $this->artisan('app:test-level-cap-notification', ['names' => ['Arthas']])
            ->expectsOutput('Test notification sent to Discord tbc_chat channel.')
            ->expectsOutput('Character names: Arthas')
            ->assertSuccessful();

        Notification::assertSentTo(
            new DiscordNotifiable('tbc_chat'),
            LevelCapAchieved::class,
            function (LevelCapAchieved $notification) {
                return $notification->characterNames === ['Arthas'];
            }
        );
    }

    public function test_it_sends_notification_with_multiple_names(): void
    {
        Notification::fake();

        $this->artisan('app:test-level-cap-notification', ['names' => ['Arthas', 'Jaina', 'Thrall']])
            ->expectsOutput('Test notification sent to Discord tbc_chat channel.')
            ->expectsOutput('Character names: Arthas, Jaina, Thrall')
            ->assertSuccessful();

        Notification::assertSentTo(
            new DiscordNotifiable('tbc_chat'),
            LevelCapAchieved::class,
            function (LevelCapAchieved $notification) {
                return $notification->characterNames === ['Arthas', 'Jaina', 'Thrall'];
            }
        );
    }

    public function test_it_fails_when_no_names_provided(): void
    {
        Notification::fake();

        $this->artisan('app:test-level-cap-notification', ['names' => []])
            ->expectsOutput('At least one character name is required.')
            ->assertFailed();

        Notification::assertNothingSent();
    }
}
