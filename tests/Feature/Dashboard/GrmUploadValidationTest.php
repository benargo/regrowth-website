<?php

namespace Tests\Feature\Dashboard;

use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Jobs\ProcessGrmUpload;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\DashboardTestCase;

#[Group('characters')]
class GrmUploadValidationTest extends DashboardTestCase
{
    #[Test]
    public function upload_requires_authentication(): void
    {
        $response = $this->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function upload_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function upload_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function upload_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function upload_allows_officer_users(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertRedirect();
    }

    #[Test]
    public function upload_dispatches_processing_job(): void
    {
        Queue::fake();

        $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        Queue::assertPushed(ProcessGrmUpload::class);
    }

    // ==================== form — member count ====================

    #[Test]
    public function upload_form_member_count_reflects_guild_roster(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetGuildRosterRequest::class => MockResponse::make(body: [
                'guild' => ['key' => ['href' => 'https://example.test/guild'], 'name' => 'Wild Growth', 'id' => 1, 'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike']],
                'members' => [
                    [
                        'character' => [
                            'id' => 1,
                            'name' => 'Alpha',
                            'level' => 70,
                            'playable_class' => ['key' => ['href' => 'https://example.test/class/1'], 'name' => 'Warrior', 'id' => 1],
                            'playable_race' => ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Human', 'id' => 1],
                            'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike'],
                        ],
                        'rank' => 0,
                    ],
                    [
                        'character' => [
                            'id' => 2,
                            'name' => 'Bravo',
                            'level' => 70,
                            'playable_class' => ['key' => ['href' => 'https://example.test/class/2'], 'name' => 'Paladin', 'id' => 2],
                            'playable_race' => ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Human', 'id' => 1],
                            'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike'],
                        ],
                        'rank' => 1,
                    ],
                ],
            ], status: 200),
        ]);

        $response = $this->actingAs($this->officer)->get(route('management.grm-upload.form'));
        $pageData = $response->viewData('page');

        $partialResponse = $this->actingAs($this->officer)->get(route('management.grm-upload.form'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'Manage/GrmUpload/Form',
            'X-Inertia-Partial-Data' => 'memberCount',
        ]);

        $partialResponse->assertOk();
        $partialResponse->assertJsonPath('props.memberCount', 2);
    }

    // ==================== upload — validation ====================

    #[Group('validation')]
    #[Test]
    public function upload_validates_grm_data_required(): void
    {

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), []);

        $response->assertSessionHasErrors(['grm_data']);
    }

    #[Group('validation')]
    #[Test]
    public function upload_validates_csv_has_header_and_data_rows(): void
    {

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => 'Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts',
        ]);

        $response->assertSessionHasErrors(['grm_data']);
    }

    #[Group('validation')]
    #[Test]
    public function upload_validates_required_headers_present(): void
    {

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank\nTestChar,Raider",
        ]);

        $response->assertSessionHasErrors(['grm_data']);
    }

    // ==================== upload — delimiter handling ====================

    #[Test]
    public function upload_accepts_comma_delimited_csv(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertSessionDoesntHaveErrors(['grm_data']);
    }

    #[Test]
    public function upload_accepts_semicolon_delimited_csv(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\nTestChar;Raider;80;1;Main;",
        ]);

        $response->assertSessionDoesntHaveErrors(['grm_data']);
    }

    #[Group('validation')]
    #[Test]
    public function upload_rejects_csv_without_valid_delimiter(): void
    {

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name|Rank|Level|Last Online (Days)|Main/Alt|Player Alts\nTestChar|Raider|80|1|Main|",
        ]);

        $response->assertSessionHasErrors(['grm_data']);
    }

    // ==================== upload — job payload ====================

    #[Test]
    public function upload_passes_uploading_user_id_to_job(): void
    {
        Queue::fake();

        $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        Queue::assertPushed(ProcessGrmUpload::class, function (ProcessGrmUpload $job) {
            return $job->userId === $this->officer->id;
        });
    }

    #[Test]
    public function upload_passes_correct_data_to_job(): void
    {
        Queue::fake();

        $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,AltOne;AltTwo",
        ]);

        Queue::assertPushed(ProcessGrmUpload::class, function ($job) {
            $data = $job->grmData;

            return $data['delimiter'] === ','
                && count($data['rows']) === 1
                && $data['rows'][0]['Name'] === 'TestChar'
                && $data['rows'][0]['Player Alts'] === 'AltOne;AltTwo';
        });
    }

    #[Test]
    public function upload_detects_semicolon_delimiter_when_more_common(): void
    {
        Queue::fake();

        $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\nTestChar;Raider;80;1;Main;AltOne,AltTwo",
        ]);

        Queue::assertPushed(ProcessGrmUpload::class, function ($job) {
            return $job->grmData['delimiter'] === ';';
        });
    }
}
