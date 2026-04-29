<?php

namespace Tests\Feature\DailyQuests;

use App\Contracts\Notifications\DiscordMessage;
use App\Models\DiscordNotification;
use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\DailyQuestsMessage;
use App\Services\Discord\Payloads\MessagePayload;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

class OtherDiscordNotification extends Notification implements DiscordMessage
{
    public function via(object $notifiable): string
    {
        return '';
    }

    public function toMessage(): MessagePayload
    {
        return MessagePayload::from([]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [];
    }

    public function updates(): ?DiscordNotification
    {
        return null;
    }

    public function sender(): ?Authenticatable
    {
        return null;
    }
}

class AuditLogTest extends DashboardTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DiscordRole::find('829021769448816691')->givePermissionTo(
            Permission::firstOrCreate(['name' => 'audit-daily-quests', 'guard_name' => 'web'])
        );
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.daily-quests.audit'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function it_requires_audit_daily_quests_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard.daily-quests.audit'));

        $response->assertForbidden();
    }

    #[Test]
    public function it_displays_paginated_discord_notifications(): void
    {
        DiscordNotification::factory()->count(3)->create(['type' => DailyQuestsMessage::class]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.daily-quests.audit'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/DailyQuestsAuditLog')
            ->has('entries.data', 3)
        );
    }

    #[Test]
    public function it_only_shows_daily_quests_notifications(): void
    {
        DiscordNotification::factory()->create(['type' => DailyQuestsMessage::class]);
        DiscordNotification::factory()->create(['type' => OtherDiscordNotification::class]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.daily-quests.audit'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
        );
    }

    #[Test]
    public function it_orders_by_latest_first(): void
    {
        $older = DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => now()->subDay(),
        ]);
        $newer = DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.daily-quests.audit'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('entries.data', 2)
            ->where('entries.data.0.id', $newer->id)
            ->where('entries.data.1.id', $older->id)
        );
    }
}
