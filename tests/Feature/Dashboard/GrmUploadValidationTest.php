<?php

namespace Tests\Feature\Dashboard;

use App\Jobs\ProcessGrmUpload;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

class GrmUploadValidationTest extends DashboardTestCase
{
    #[Test]
    public function upload_requires_authentication(): void
    {
        $response = $this->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function upload_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function upload_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function upload_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function upload_allows_officer_users(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->officer)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertRedirect();
    }

    #[Test]
    public function upload_dispatches_processing_job(): void
    {
        Queue::fake();

        $this->actingAs($this->officer)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        Queue::assertPushed(ProcessGrmUpload::class);
    }

    #[Test]
    public function upload_validates_grm_data_required(): void
    {

        $response = $this->actingAs($this->officer)->post(route('dashboard.grm-upload.upload'), []);

        $response->assertSessionHasErrors(['grm_data']);
    }

    #[Test]
    public function upload_validates_csv_has_header_and_data_rows(): void
    {

        $response = $this->actingAs($this->officer)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => 'Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts',
        ]);

        $response->assertSessionHasErrors(['grm_data']);
    }

    #[Test]
    public function upload_validates_required_headers_present(): void
    {

        $response = $this->actingAs($this->officer)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank\nTestChar,Raider",
        ]);

        $response->assertSessionHasErrors(['grm_data']);
    }

    #[Test]
    public function upload_accepts_comma_delimited_csv(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->officer)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $response->assertSessionDoesntHaveErrors(['grm_data']);
    }

    #[Test]
    public function upload_accepts_semicolon_delimited_csv(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->officer)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\nTestChar;Raider;80;1;Main;",
        ]);

        $response->assertSessionDoesntHaveErrors(['grm_data']);
    }

    #[Test]
    public function upload_rejects_csv_without_valid_delimiter(): void
    {

        $response = $this->actingAs($this->officer)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name|Rank|Level|Last Online (Days)|Main/Alt|Player Alts\nTestChar|Raider|80|1|Main|",
        ]);

        $response->assertSessionHasErrors(['grm_data']);
    }

    #[Test]
    public function upload_initializes_progress_cache(): void
    {
        Queue::fake();

        $this->actingAs($this->officer)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name,Rank,Level,Last Online (Days),Main/Alt,Player Alts\nTestChar,Raider,80,1,Main,",
        ]);

        $this->assertTrue(Cache::has(ProcessGrmUpload::PROGRESS_CACHE_KEY));
        $this->assertEquals('queued', Cache::get(ProcessGrmUpload::PROGRESS_CACHE_KEY)['status']);
    }

    #[Test]
    public function upload_passes_correct_data_to_job(): void
    {
        Queue::fake();

        $this->actingAs($this->officer)->post(route('dashboard.grm-upload.upload'), [
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

        $this->actingAs($this->officer)->post(route('dashboard.grm-upload.upload'), [
            'grm_data' => "Name;Rank;Level;Last Online (Days);Main/Alt;Player Alts\nTestChar;Raider;80;1;Main;AltOne,AltTwo",
        ]);

        Queue::assertPushed(ProcessGrmUpload::class, function ($job) {
            return $job->grmData['delimiter'] === ';';
        });
    }
}
