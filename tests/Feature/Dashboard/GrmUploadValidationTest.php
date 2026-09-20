<?php

namespace Tests\Feature\Dashboard;

use App\Jobs\ProcessGrmUpload;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\Support\DashboardTestCase;

#[Group('characters')]
class GrmUploadValidationTest extends DashboardTestCase
{
    use MocksBlizzardServices;

    #[Test]
    public function upload_requires_authentication(): void
    {
        $version = GameVersion::factory()->create();

        $response = $this->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
            'game_version_id' => $version->id,
        ]);

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function upload_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $version = GameVersion::factory()->create();

        $response = $this->actingAs($user)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
            'game_version_id' => $version->id,
        ]);

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function upload_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $version = GameVersion::factory()->create();

        $response = $this->actingAs($user)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
            'game_version_id' => $version->id,
        ]);

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function upload_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $version = GameVersion::factory()->create();

        $response = $this->actingAs($user)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
            'game_version_id' => $version->id,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function upload_allows_officer_users(): void
    {
        Queue::fake();
        $version = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
            'game_version_id' => $version->id,
        ]);

        $response->assertRedirect();
    }

    #[Test]
    public function upload_dispatches_processing_job(): void
    {
        Queue::fake();
        $version = GameVersion::factory()->create();

        $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
            'game_version_id' => $version->id,
        ]);

        Queue::assertPushed(ProcessGrmUpload::class);
    }

    // ==================== form — member count ====================

    #[Test]
    public function upload_form_member_count_reflects_guild_roster(): void
    {
        GameVersion::factory()->create(['realm' => 'thunderstrike']);

        $this->mockGetGuildRoster(['members' => [
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
        ]]);
        $this->applyBlizzardMocks();

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

    #[Test]
    public function upload_form_exposes_the_list_of_game_versions(): void
    {
        $versionA = GameVersion::factory()->create(['title' => 'Classic', 'release_date' => now()->subYear()]);
        $versionB = GameVersion::factory()->create(['title' => 'Season of Discovery', 'release_date' => now()]);

        $response = $this->actingAs($this->officer)->get(route('management.grm-upload.form'));

        $response->assertInertia(fn ($page) => $page
            ->has('gameVersions', 2)
            ->where('gameVersions.0.title', 'Classic')
            ->where('gameVersions.1.title', 'Season of Discovery')
        );
    }

    #[Test]
    public function upload_form_excludes_game_versions_without_a_blizzard_namespace(): void
    {
        GameVersion::factory()->create(['title' => 'Classic', 'blizzard_namespace' => null]);
        GameVersion::factory()->create(['title' => 'Season of Discovery']);

        $response = $this->actingAs($this->officer)->get(route('management.grm-upload.form'));

        $response->assertInertia(fn ($page) => $page
            ->has('gameVersions', 1)
            ->where('gameVersions.0.title', 'Season of Discovery')
        );
    }

    #[Test]
    public function upload_form_member_count_uses_the_selected_game_version(): void
    {
        GameVersion::factory()->create(['title' => 'Alpha Version', 'realm' => 'thunderstrike']);
        $selected = GameVersion::factory()->create(['title' => 'Beta Version', 'realm' => 'proudmoore']);

        $this->mockGetGuildRoster(['members' => [
            [
                'character' => [
                    'id' => 1,
                    'name' => 'Alpha',
                    'level' => 70,
                    'playable_class' => ['key' => ['href' => 'https://example.test/class/1'], 'name' => 'Warrior', 'id' => 1],
                    'playable_race' => ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Human', 'id' => 1],
                    'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Proudmoore', 'id' => 1, 'slug' => 'proudmoore'],
                ],
                'rank' => 0,
            ],
        ]]);
        $this->applyBlizzardMocks();

        $response = $this->actingAs($this->officer)->get(route('management.grm-upload.form'));
        $pageData = $response->viewData('page');

        $partialResponse = $this->actingAs($this->officer)->get(route('management.grm-upload.form', ['game_version_id' => $selected->id]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'Manage/GrmUpload/Form',
            'X-Inertia-Partial-Data' => 'memberCount',
        ]);

        $partialResponse->assertOk();
        $partialResponse->assertJsonPath('props.memberCount', 1);
    }

    #[Test]
    public function upload_form_member_count_uses_the_first_game_versions_realm(): void
    {
        GameVersion::factory()->create(['title' => 'Alpha Version', 'realm' => 'thunderstrike']);
        $this->mockGetGuildRoster(['members' => [
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
        ]]);
        $this->applyBlizzardMocks();

        $response = $this->actingAs($this->officer)->get(route('management.grm-upload.form'));
        $pageData = $response->viewData('page');

        $partialResponse = $this->actingAs($this->officer)->get(route('management.grm-upload.form'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'Manage/GrmUpload/Form',
            'X-Inertia-Partial-Data' => 'memberCount',
        ]);

        $partialResponse->assertOk();
        $partialResponse->assertJsonPath('props.memberCount', 1);
    }

    #[Test]
    public function upload_form_member_count_is_null_when_no_game_version_has_a_realm(): void
    {
        GameVersion::factory()->create(['title' => 'Alpha Version', 'realm' => null]);

        $response = $this->actingAs($this->officer)->get(route('management.grm-upload.form'));
        $pageData = $response->viewData('page');

        $partialResponse = $this->actingAs($this->officer)->get(route('management.grm-upload.form'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'Manage/GrmUpload/Form',
            'X-Inertia-Partial-Data' => 'memberCount',
        ]);

        $partialResponse->assertOk();
        $partialResponse->assertJsonPath('props.memberCount', null);
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
        $version = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => 'Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts',
            'game_version_id' => $version->id,
        ]);

        $response->assertSessionHasErrors(['grm_data']);
    }

    #[Group('validation')]
    #[Test]
    public function upload_validates_required_headers_present(): void
    {
        $version = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank\nTestChar,Raider",
            'game_version_id' => $version->id,
        ]);

        $response->assertSessionHasErrors(['grm_data']);
    }

    // ==================== upload — delimiter handling ====================

    #[Test]
    public function upload_accepts_comma_delimited_csv(): void
    {
        Queue::fake();
        $version = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
            'game_version_id' => $version->id,
        ]);

        $response->assertSessionDoesntHaveErrors(['grm_data']);
    }

    #[Test]
    public function upload_accepts_semicolon_delimited_csv(): void
    {
        Queue::fake();
        $version = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\nTestChar;Raider;80;1;Main;",
            'game_version_id' => $version->id,
        ]);

        $response->assertSessionDoesntHaveErrors(['grm_data']);
    }

    #[Group('validation')]
    #[Test]
    public function upload_rejects_csv_without_valid_delimiter(): void
    {
        $version = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name|Rank|Level|Last Online (Days)|Main/Alt|Player Alts\nTestChar|Raider|80|1|Main|",
            'game_version_id' => $version->id,
        ]);

        $response->assertSessionHasErrors(['grm_data']);
    }

    #[Group('validation')]
    #[Test]
    public function upload_validates_game_version_id_required(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertSessionHasErrors(['game_version_id']);
    }

    #[Group('validation')]
    #[Test]
    public function upload_validates_game_version_id_must_exist(): void
    {
        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
            'game_version_id' => 999999,
        ]);

        $response->assertSessionHasErrors(['game_version_id']);
    }

    #[Test]
    public function upload_accepts_a_valid_game_version_id(): void
    {
        Queue::fake();
        $version = GameVersion::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
            'game_version_id' => $version->id,
        ]);

        $response->assertSessionDoesntHaveErrors(['game_version_id']);
    }

    // ==================== upload — job payload ====================

    #[Test]
    public function upload_passes_uploading_user_id_to_job(): void
    {
        Queue::fake();
        $version = GameVersion::factory()->create();

        $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
            'game_version_id' => $version->id,
        ]);

        Queue::assertPushed(ProcessGrmUpload::class, function (ProcessGrmUpload $job) {
            return $job->userId === $this->officer->id;
        });
    }

    #[Test]
    public function upload_passes_correct_data_to_job(): void
    {
        Queue::fake();
        $version = GameVersion::factory()->create();

        $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,AltOne;AltTwo",
            'game_version_id' => $version->id,
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
        $version = GameVersion::factory()->create();

        $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\nTestChar;Raider;80;1;Main;AltOne,AltTwo",
            'game_version_id' => $version->id,
        ]);

        Queue::assertPushed(ProcessGrmUpload::class, function ($job) {
            return $job->grmData['delimiter'] === ';';
        });
    }

    #[Test]
    public function upload_passes_the_selected_game_version_to_the_job(): void
    {
        Queue::fake();
        $version = GameVersion::factory()->create();

        $this->actingAs($this->officer)->post(route('management.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
            'game_version_id' => $version->id,
        ]);

        Queue::assertPushed(ProcessGrmUpload::class, function (ProcessGrmUpload $job) use ($version) {
            return $job->gameVersionId === $version->id;
        });
    }
}
