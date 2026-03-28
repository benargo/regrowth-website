<?php

namespace Tests\Feature\Dashboard;

use App\Jobs\ProcessGrmUpload;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

class GrmUploadStatusTest extends DashboardTestCase
{
    #[Test]
    public function status_endpoint_requires_authentication(): void
    {
        $response = $this->getJson(route('dashboard.grm-upload.status'));

        $response->assertUnauthorized();
    }

    #[Test]
    public function status_endpoint_forbidden_for_non_officers(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->getJson(route('dashboard.grm-upload.status'));

        $response->assertForbidden();
    }

    #[Test]
    public function status_endpoint_returns_unknown_when_no_cache(): void
    {
        Cache::forget(ProcessGrmUpload::PROGRESS_CACHE_KEY);

        $response = $this->actingAs($this->officer)->getJson(route('dashboard.grm-upload.status'));

        $response->assertOk()->assertJson(['status' => 'unknown']);
    }

    #[Test]
    public function status_endpoint_returns_current_progress(): void
    {

        Cache::put(ProcessGrmUpload::PROGRESS_CACHE_KEY, [
            'status' => 'processing',
            'step' => 1,
            'total' => 3,
            'message' => 'Processing GRM roster data...',
            'processedCount' => 0,
            'skippedCount' => 0,
            'warningCount' => 0,
            'errorCount' => 0,
            'errors' => [],
        ]);

        $response = $this->actingAs($this->officer)->getJson(route('dashboard.grm-upload.status'));

        $response->assertOk()
            ->assertJson([
                'status' => 'processing',
                'step' => 1,
                'total' => 3,
                'message' => 'Processing GRM roster data...',
            ]);
    }

    #[Test]
    public function status_endpoint_returns_completed_state(): void
    {

        Cache::put(ProcessGrmUpload::PROGRESS_CACHE_KEY, [
            'status' => 'completed',
            'step' => 3,
            'total' => 3,
            'message' => 'Upload complete!',
            'processedCount' => 150,
            'skippedCount' => 5,
            'warningCount' => 2,
            'errorCount' => 0,
            'errors' => [],
        ]);

        $response = $this->actingAs($this->officer)->getJson(route('dashboard.grm-upload.status'));

        $response->assertOk()
            ->assertJson([
                'status' => 'completed',
                'step' => 3,
                'processedCount' => 150,
            ]);
    }

    #[Test]
    public function status_endpoint_returns_failed_state(): void
    {

        Cache::put(ProcessGrmUpload::PROGRESS_CACHE_KEY, [
            'status' => 'failed',
            'step' => 3,
            'total' => 3,
            'message' => 'Upload completed with errors.',
            'processedCount' => 3,
            'skippedCount' => 0,
            'warningCount' => 0,
            'errorCount' => 2,
            'errors' => ['CharA: API error', 'CharB: not found'],
        ]);

        $response = $this->actingAs($this->officer)->getJson(route('dashboard.grm-upload.status'));

        $response->assertOk()
            ->assertJson([
                'status' => 'failed',
                'errorCount' => 2,
            ]);
    }
}
