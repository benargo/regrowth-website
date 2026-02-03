<?php

namespace Tests\Feature\Dashboard;

use App\Jobs\ProcessGrmUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GrmUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_requires_authentication(): void
    {
        $response = $this->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertRedirect('/login');
    }

    public function test_upload_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertForbidden();
    }

    public function test_upload_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertForbidden();
    }

    public function test_upload_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertForbidden();
    }

    public function test_upload_allows_officer_users(): void
    {
        Queue::fake();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertRedirect();
    }

    public function test_upload_dispatches_processing_job(): void
    {
        Queue::fake();
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        Queue::assertPushed(ProcessGrmUpload::class);
    }

    public function test_upload_validates_grm_data_required(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), []);

        $response->assertSessionHasErrors(['grm_data']);
    }

    public function test_upload_validates_csv_has_header_and_data_rows(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => 'Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts',
        ]);

        $response->assertSessionHasErrors(['grm_data']);
    }

    public function test_upload_validates_required_headers_present(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank\nTestChar,Raider",
        ]);

        $response->assertSessionHasErrors(['grm_data']);
    }

    public function test_upload_accepts_comma_delimited_csv(): void
    {
        Queue::fake();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertSessionDoesntHaveErrors(['grm_data']);
    }

    public function test_upload_accepts_semicolon_delimited_csv(): void
    {
        Queue::fake();
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\nTestChar;Raider;80;1;Main;",
        ]);

        $response->assertSessionDoesntHaveErrors(['grm_data']);
    }

    public function test_upload_rejects_csv_without_valid_delimiter(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name|Rank|Level|Last Online (Days)|Main/Alt|Player Alts\nTestChar|Raider|80|1|Main|",
        ]);

        $response->assertSessionHasErrors(['grm_data']);
    }

    public function test_upload_passes_correct_data_to_job(): void
    {
        Queue::fake();
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
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

    public function test_upload_detects_semicolon_delimiter_when_more_common(): void
    {
        Queue::fake();
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\nTestChar;Raider;80;1;Main;AltOne,AltTwo",
        ]);

        Queue::assertPushed(ProcessGrmUpload::class, function ($job) {
            return $job->grmData['delimiter'] === ';';
        });
    }
}
