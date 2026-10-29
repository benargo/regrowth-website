<?php

namespace Tests\Feature\Actions\Discord;

use App\Actions\Discord\UpsertDiscordChannel;
use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use App\Http\Integrations\Discord\Enums\ChannelType;
use App\Models\DiscordChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class UpsertDiscordChannelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_channel(): void
    {
        $channel = UpsertDiscordChannel::run(ChannelData::from([
            'id' => '111111111111111111',
            'type' => 0,
            'name' => 'raids',
            'position' => 4,
            'parent_id' => '222222222222222222',
            'topic' => 'Raid sign-ups',
            'nsfw' => true,
        ]));

        $this->assertSame('111111111111111111', $channel->id);
        $this->assertDatabaseHas('discord_channels', [
            'id' => '111111111111111111',
            'name' => 'raids',
            'type' => ChannelType::GuildText->value,
            'position' => 4,
            'parent_id' => '222222222222222222',
            'topic' => 'Raid sign-ups',
            'nsfw' => true,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_updates_an_existing_channel(): void
    {
        $existing = DiscordChannel::factory()->create(['name' => 'old-name']);

        UpsertDiscordChannel::run(ChannelData::from(['id' => $existing->id, 'type' => 0, 'name' => 'new-name']));

        $this->assertDatabaseCount('discord_channels', 1);
        $this->assertSame('new-name', $existing->fresh()->name);
    }

    #[Test]
    public function it_restores_a_trashed_channel(): void
    {
        $existing = DiscordChannel::factory()->trashed()->create();

        $channel = UpsertDiscordChannel::run(ChannelData::from(['id' => $existing->id, 'type' => 0, 'name' => 'raids']));

        $this->assertFalse($channel->trashed());
        $this->assertDatabaseHas('discord_channels', ['id' => $existing->id, 'deleted_at' => null]);
    }

    #[Test]
    public function it_maps_optional_fields_to_null_zero_and_false(): void
    {
        UpsertDiscordChannel::run(ChannelData::from(['id' => '1']));

        $this->assertDatabaseHas('discord_channels', [
            'id' => '1',
            'name' => null,
            'type' => null,
            'parent_id' => null,
            'topic' => null,
            'position' => 0,
            'nsfw' => false,
        ]);
    }
}
