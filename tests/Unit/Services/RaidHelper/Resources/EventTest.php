<?php

namespace Tests\Unit\Services\RaidHelper\Resources;

use App\Services\RaidHelper\Resources\Event;
use App\Services\RaidHelper\Resources\EventAdvancedSettings;
use App\Services\RaidHelper\Resources\EventClass;
use App\Services\RaidHelper\Resources\EventRole;
use App\Services\RaidHelper\Resources\SignUp;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventTest extends TestCase
{
    private function payload(): array
    {
        return [
            'id' => '334385199974967042',
            'serverId' => '290926798999357440',
            'leaderId' => '80351110224678912',
            'leaderName' => 'Thrall',
            'channelId' => '290926798999357250',
            'channelName' => 'raid-signups',
            'channelType' => 'GUILD_TEXT',
            'templateId' => 'tmpl-abc',
            'templateEmoteId' => '1234567890',
            'title' => 'MC Raid Night',
            'description' => 'Come prepared with consumables.',
            'startTime' => 1746316800,
            'endTime' => 1746327600,
            'closingTime' => 1746313200,
            'date' => '2026-05-04',
            'time' => '20:00',
            'advancedSettings' => [],
            'classes' => [],
            'roles' => [],
            'signUps' => [],
            'lastUpdated' => 1746230400,
            'color' => '255,0,0',
        ];
    }

    #[Test]
    public function it_constructs_from_a_payload_with_required_fields(): void
    {
        $event = Event::from($this->payload());

        $this->assertSame('334385199974967042', $event->id);
        $this->assertSame('290926798999357440', $event->serverId);
        $this->assertSame('80351110224678912', $event->leaderId);
        $this->assertSame('Thrall', $event->leaderName);
        $this->assertSame('290926798999357250', $event->channelId);
        $this->assertSame('raid-signups', $event->channelName);
        $this->assertSame('GUILD_TEXT', $event->channelType);
        $this->assertSame('tmpl-abc', $event->templateId);
        $this->assertSame('1234567890', $event->templateEmoteId);
        $this->assertSame('MC Raid Night', $event->title);
        $this->assertSame('Come prepared with consumables.', $event->description);
        $this->assertSame(1746316800, $event->startTime);
        $this->assertSame(1746327600, $event->endTime);
        $this->assertSame(1746313200, $event->closingTime);
        $this->assertSame('2026-05-04', $event->date);
        $this->assertSame('20:00', $event->time);
        $this->assertSame(1746230400, $event->lastUpdated);
        $this->assertSame('255,0,0', $event->color);
    }

    #[Test]
    public function softres_id_defaults_to_null_when_omitted(): void
    {
        $event = Event::from($this->payload());

        $this->assertNull($event->softresId);
    }

    #[Test]
    public function it_stores_softres_id_when_provided(): void
    {
        $event = Event::from([...$this->payload(), 'softresId' => 'softres-xyz']);

        $this->assertSame('softres-xyz', $event->softresId);
    }

    #[Test]
    public function it_hydrates_advanced_settings_as_an_advanced_settings_instance(): void
    {
        $event = Event::from([...$this->payload(), 'advancedSettings' => ['duration' => 180]]);

        $this->assertInstanceOf(EventAdvancedSettings::class, $event->advancedSettings);
        $this->assertSame(180, $event->advancedSettings->duration);
    }

    #[Test]
    public function it_hydrates_classes_as_raid_class_instances(): void
    {
        $event = Event::from([
            ...$this->payload(),
            'classes' => [[
                'name' => 'Druid',
                'limit' => '5',
                'emoteId' => '1111111111',
                'type' => 'primary',
                'specs' => [],
            ]],
        ]);

        $this->assertCount(1, $event->classes);
        $this->assertInstanceOf(EventClass::class, $event->classes[0]);
        $this->assertSame('Druid', $event->classes[0]->name);
    }

    #[Test]
    public function it_hydrates_roles_as_role_instances(): void
    {
        $event = Event::from([
            ...$this->payload(),
            'roles' => [[
                'name' => 'Healer',
                'limit' => '10',
                'emoteId' => '9999999999',
            ]],
        ]);

        $this->assertCount(1, $event->roles);
        $this->assertInstanceOf(EventRole::class, $event->roles[0]);
        $this->assertSame('Healer', $event->roles[0]->name);
    }

    #[Test]
    public function it_hydrates_sign_ups_as_sign_up_instances(): void
    {
        $event = Event::from([
            ...$this->payload(),
            'signUps' => [[
                'name' => 'Jaina',
                'id' => 1,
                'userId' => '80351110224678912',
                'status' => 'primary',
                'entryTime' => 1746316800,
                'position' => 1,
            ]],
        ]);

        $this->assertCount(1, $event->signUps);
        $this->assertInstanceOf(SignUp::class, $event->signUps[0]);
        $this->assertSame('Jaina', $event->signUps[0]->name);
    }

    #[Test]
    public function to_array_produces_snake_case_keys(): void
    {
        $array = Event::from($this->payload())->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('server_id', $array);
        $this->assertArrayHasKey('leader_id', $array);
        $this->assertArrayHasKey('leader_name', $array);
        $this->assertArrayHasKey('channel_id', $array);
        $this->assertArrayHasKey('channel_name', $array);
        $this->assertArrayHasKey('channel_type', $array);
        $this->assertArrayHasKey('template_id', $array);
        $this->assertArrayHasKey('template_emote_id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('start_time', $array);
        $this->assertArrayHasKey('end_time', $array);
        $this->assertArrayHasKey('closing_time', $array);
        $this->assertArrayHasKey('date', $array);
        $this->assertArrayHasKey('time', $array);
        $this->assertArrayHasKey('advanced_settings', $array);
        $this->assertArrayHasKey('classes', $array);
        $this->assertArrayHasKey('roles', $array);
        $this->assertArrayHasKey('sign_ups', $array);
        $this->assertArrayHasKey('last_updated', $array);
        $this->assertArrayHasKey('color', $array);
    }
}
