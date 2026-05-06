<?php

namespace Tests\Unit\Services\RaidHelper\Resources;

use App\Models\User;
use App\Services\RaidHelper\Resources\Event;
use App\Services\RaidHelper\Resources\EventAdvancedSettings;
use App\Services\RaidHelper\Resources\EventClass;
use App\Services\RaidHelper\Resources\EventRole;
use App\Services\RaidHelper\Resources\SignUp;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Minimal payload as returned by the listing endpoint (/events).
     *
     * @return array<string, mixed>
     */
    private function listingPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => '334385199974967042',
            'channelId' => '290926798999357250',
            'leaderId' => '80351110224678912',
            'leaderName' => 'Thrall',
            'title' => 'MC Raid Night',
            'description' => 'Come prepared with consumables.',
            'startTime' => 1746316800,
            'endTime' => 1746327600,
            'closingTime' => 1746313200,
            'lastUpdated' => 1746230400,
            'color' => '255,0,0',
        ], $overrides);
    }

    /**
     * Full payload as returned by the detail endpoint (/events/{id}).
     *
     * @return array<string, mixed>
     */
    private function detailPayload(array $overrides = []): array
    {
        return array_merge($this->listingPayload(), [
            'serverId' => '290926798999357440',
            'channelName' => 'raid-signups',
            'channelType' => 'GUILD_TEXT',
            'templateId' => 'tmpl-abc',
            'templateEmoteId' => '1234567890',
            'date' => '2026-05-04',
            'time' => '20:00',
            'advancedSettings' => [],
            'classes' => [],
            'roles' => [],
            'signUps' => [],
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Required fields (both endpoints)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_constructs_from_a_listing_payload(): void
    {
        $event = Event::from($this->listingPayload());

        $this->assertSame('334385199974967042', $event->id);
        $this->assertSame('290926798999357250', $event->channelId);
        $this->assertSame('80351110224678912', $event->leaderId);
        $this->assertSame('Thrall', $event->leaderName);
        $this->assertSame('MC Raid Night', $event->title);
        $this->assertSame('Come prepared with consumables.', $event->description);
        $this->assertSame('255,0,0', $event->color);
    }

    #[Test]
    public function it_constructs_from_a_detail_payload(): void
    {
        $event = Event::from($this->detailPayload());

        $this->assertSame('334385199974967042', $event->id);
        $this->assertSame('290926798999357440', $event->serverId);
        $this->assertSame('raid-signups', $event->channelName);
        $this->assertSame('GUILD_TEXT', $event->channelType);
        $this->assertSame('tmpl-abc', $event->templateId);
        $this->assertSame('1234567890', $event->templateEmoteId);
        $this->assertSame('2026-05-04', $event->date);
        $this->assertSame('20:00', $event->time);
    }

    // -------------------------------------------------------------------------
    // Carbon casting
    // -------------------------------------------------------------------------

    #[Test]
    public function it_casts_timestamp_fields_to_carbon_instances(): void
    {
        $event = Event::from($this->listingPayload());

        $this->assertInstanceOf(CarbonInterface::class, $event->startTime);
        $this->assertInstanceOf(CarbonInterface::class, $event->endTime);
        $this->assertInstanceOf(CarbonInterface::class, $event->closingTime);
        $this->assertInstanceOf(CarbonInterface::class, $event->lastUpdated);
    }

    #[Test]
    public function it_casts_timestamp_values_correctly(): void
    {
        $event = Event::from($this->listingPayload());

        $this->assertSame(1746316800, $event->startTime->unix());
        $this->assertSame(1746327600, $event->endTime->unix());
        $this->assertSame(1746313200, $event->closingTime->unix());
        $this->assertSame(1746230400, $event->lastUpdated->unix());
    }

    // -------------------------------------------------------------------------
    // Optional nullable fields
    // -------------------------------------------------------------------------

    #[Test]
    public function detail_only_fields_default_to_null_when_omitted(): void
    {
        $event = Event::from($this->listingPayload());

        $this->assertNull($event->serverId);
        $this->assertNull($event->channelName);
        $this->assertNull($event->channelType);
        $this->assertNull($event->templateEmoteId);
        $this->assertNull($event->date);
        $this->assertNull($event->time);
        $this->assertNull($event->advancedSettings);
        $this->assertNull($event->classes);
        $this->assertNull($event->roles);
        $this->assertNull($event->signUps);
        $this->assertNull($event->scheduledId);
        $this->assertNull($event->displayTitle);
        $this->assertNull($event->announcements);
    }

    #[Test]
    public function optional_string_fields_default_to_null_when_omitted(): void
    {
        $event = Event::from($this->listingPayload());

        $this->assertNull($event->templateId);
        $this->assertNull($event->softresId);
        $this->assertNull($event->imageUrl);
    }

    #[Test]
    public function sign_up_count_defaults_to_null_when_omitted(): void
    {
        $event = Event::from($this->listingPayload());

        $this->assertNull($event->signUpCount);
    }

    #[Test]
    public function it_stores_softres_id_when_provided(): void
    {
        $event = Event::from($this->listingPayload(['softresId' => 'softres-xyz']));

        $this->assertSame('softres-xyz', $event->softresId);
    }

    #[Test]
    public function it_stores_image_url_when_provided(): void
    {
        $event = Event::from($this->listingPayload(['imageUrl' => 'https://example.com/raid.png']));

        $this->assertSame('https://example.com/raid.png', $event->imageUrl);
    }

    #[Test]
    public function it_stores_sign_up_count_when_provided(): void
    {
        $event = Event::from($this->listingPayload(['signUpCount' => 12]));

        $this->assertSame(12, $event->signUpCount);

        $event = Event::from($this->listingPayload(['signUpCount' => '12']));
        $this->assertSame(12, $event->signUpCount);
    }

    #[Test]
    public function it_stores_sign_ups_when_provided(): void
    {
        $event = Event::from($this->listingPayload([
            'signUps' => [[
                'name' => 'Jaina',
                'id' => 1,
                'userId' => '80351110224678912',
                'status' => 'primary',
                'entryTime' => 1746316800,
                'position' => 1,
            ]],
        ]));

        $this->assertCount(1, $event->signUps);
        $this->assertInstanceOf(SignUp::class, $event->signUps[0]);
        $this->assertSame('Jaina', $event->signUps[0]->name);
    }

    // -------------------------------------------------------------------------
    // Nested hydration
    // -------------------------------------------------------------------------

    #[Test]
    public function it_hydrates_advanced_settings_as_an_event_advanced_settings_instance(): void
    {
        $event = Event::from($this->detailPayload(['advancedSettings' => ['duration' => 180]]));

        $this->assertInstanceOf(EventAdvancedSettings::class, $event->advancedSettings);
        $this->assertSame(180, $event->advancedSettings->duration);
    }

    #[Test]
    public function it_hydrates_classes_as_event_class_instances(): void
    {
        $event = Event::from($this->detailPayload([
            'classes' => [[
                'name' => 'Druid',
                'limit' => '5',
                'emoteId' => '1111111111',
                'type' => 'primary',
                'specs' => [],
            ]],
        ]));

        $this->assertCount(1, $event->classes);
        $this->assertInstanceOf(EventClass::class, $event->classes[0]);
        $this->assertSame('Druid', $event->classes[0]->name);
    }

    #[Test]
    public function it_hydrates_roles_as_event_role_instances(): void
    {
        $event = Event::from($this->detailPayload([
            'roles' => [[
                'name' => 'Healer',
                'limit' => '10',
                'emoteId' => '9999999999',
            ]],
        ]));

        $this->assertCount(1, $event->roles);
        $this->assertInstanceOf(EventRole::class, $event->roles[0]);
        $this->assertSame('Healer', $event->roles[0]->name);
    }

    // -------------------------------------------------------------------------
    // user()
    // -------------------------------------------------------------------------

    #[Test]
    public function user_returns_null_when_leader_id_does_not_match_a_user(): void
    {
        $event = Event::from($this->listingPayload(['leaderId' => 'nonexistent-discord-id']));

        $this->assertNull($event->user());
    }

    #[Test]
    public function user_returns_a_user_when_leader_id_matches_a_user(): void
    {
        $user = User::factory()->create(['id' => '80351110224678912']);
        $event = Event::from($this->listingPayload());

        $result = $event->user();

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->id, $result->id);
    }

    // -------------------------------------------------------------------------
    // rules()
    // -------------------------------------------------------------------------

    #[Test]
    public function rules_returns_time_ordering_constraints(): void
    {
        $rules = Event::rules();

        $this->assertArrayHasKey('startTime', $rules);
        $this->assertArrayHasKey('endTime', $rules);
        $this->assertArrayHasKey('closingTime', $rules);

        $this->assertContains('before:endTime', $rules['startTime']);
        $this->assertContains('before_or_equal:closingTime', $rules['startTime']);
        $this->assertContains('after:startTime', $rules['endTime']);
        $this->assertContains('after:closingTime', $rules['endTime']);
        $this->assertContains('before_or_equal:startTime', $rules['closingTime']);
    }

    // -------------------------------------------------------------------------
    // toArray()
    // -------------------------------------------------------------------------

    #[Test]
    public function to_array_produces_snake_case_keys_for_listing_payload(): void
    {
        $array = Event::from($this->listingPayload())->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('channel_id', $array);
        $this->assertArrayHasKey('leader_id', $array);
        $this->assertArrayHasKey('leader_name', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('start_time', $array);
        $this->assertArrayHasKey('end_time', $array);
        $this->assertArrayHasKey('closing_time', $array);
        $this->assertArrayHasKey('last_updated', $array);
        $this->assertArrayHasKey('color', $array);
    }

    #[Test]
    public function to_array_produces_snake_case_keys_for_detail_payload(): void
    {
        $array = Event::from($this->detailPayload())->toArray();

        $this->assertArrayHasKey('server_id', $array);
        $this->assertArrayHasKey('channel_name', $array);
        $this->assertArrayHasKey('channel_type', $array);
        $this->assertArrayHasKey('template_id', $array);
        $this->assertArrayHasKey('template_emote_id', $array);
        $this->assertArrayHasKey('date', $array);
        $this->assertArrayHasKey('time', $array);
        $this->assertArrayHasKey('advanced_settings', $array);
        $this->assertArrayHasKey('classes', $array);
        $this->assertArrayHasKey('roles', $array);
        $this->assertArrayHasKey('sign_ups', $array);
    }

    // -------------------------------------------------------------------------
    // New optional fields
    // -------------------------------------------------------------------------

    #[Test]
    public function new_fields_default_to_null_when_omitted(): void
    {
        $event = Event::from($this->listingPayload());

        $this->assertNull($event->scheduledId);
        $this->assertNull($event->displayTitle);
        $this->assertNull($event->announcements);
    }

    #[Test]
    public function it_stores_scheduled_id_when_provided(): void
    {
        $event = Event::from($this->listingPayload(['scheduledId' => '902879335676006421-1']));

        $this->assertSame('902879335676006421-1', $event->scheduledId);
    }

    #[Test]
    public function it_stores_display_title_when_provided(): void
    {
        $event = Event::from($this->listingPayload(['displayTitle' => 'Karazhan']));

        $this->assertSame('Karazhan', $event->displayTitle);
    }

    #[Test]
    public function it_stores_announcements_when_provided(): void
    {
        $event = Event::from($this->listingPayload(['announcements' => []]));

        $this->assertSame([], $event->announcements);
    }

    #[Test]
    public function it_maps_close_time_to_closing_time(): void
    {
        $payload = array_merge(
            array_diff_key($this->listingPayload(), ['closingTime' => null]),
            ['closeTime' => 1746313200],
        );

        $event = Event::from($payload);

        $this->assertInstanceOf(CarbonInterface::class, $event->closingTime);
        $this->assertSame(1746313200, $event->closingTime->unix());
    }

    #[Test]
    public function it_casts_sign_up_count_string_to_integer(): void
    {
        $event = Event::from($this->listingPayload(['signUpCount' => '16']));

        $this->assertSame(16, $event->signUpCount);
    }
}
