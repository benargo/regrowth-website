<?php

namespace Tests\Feature\Jobs\RegrowthAddon\Export;

use App\Jobs\RegrowthAddon\Export\BuildDataFile;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuildDataFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Storage::fake('local');
    }

    // ==========================================
    // Job Contract Tests
    // ==========================================

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new BuildDataFile);
    }

    public function test_it_uses_batchable_trait(): void
    {
        $this->assertContains(Batchable::class, class_uses_recursive(BuildDataFile::class));
    }

    public function test_it_has_correct_job_tags(): void
    {
        $this->assertEquals(['regrowth-addon', 'regrowth-addon:build'], (new BuildDataFile)->tags());
    }

    public function test_it_has_skip_if_batch_cancelled_middleware(): void
    {
        $middlewareClasses = array_map(fn ($m) => get_class($m), (new BuildDataFile)->middleware());

        $this->assertContains(SkipIfBatchCancelled::class, $middlewareClasses);
    }

    // ==========================================
    // Storage Tests
    // ==========================================

    public function test_it_writes_export_data_to_storage(): void
    {
        (new BuildDataFile)->handle();

        Storage::disk('local')->assertExists('addon/export.json');
    }

    public function test_it_writes_valid_json_to_storage(): void
    {
        (new BuildDataFile)->handle();

        $content = Storage::disk('local')->get('addon/export.json');
        $this->assertNotNull(json_decode($content, true));
    }

    // ==========================================
    // Data Structure Tests
    // ==========================================

    public function test_it_includes_all_sections_in_output(): void
    {
        (new BuildDataFile)->handle();

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertArrayHasKey('system', $data);
        $this->assertArrayHasKey('priorities', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('players', $data);
        $this->assertArrayHasKey('councillors', $data);
    }

    public function test_it_includes_system_date_generated_as_unix_timestamp(): void
    {
        Carbon::setTestNow('2025-06-01 12:00:00');

        (new BuildDataFile)->handle();

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertArrayHasKey('date_generated', $data['system']);
        $this->assertIsInt($data['system']['date_generated']);
        $this->assertEquals(Carbon::now()->unix(), $data['system']['date_generated']);
    }

    public function test_it_reads_priorities_from_cache(): void
    {
        $priorities = collect([['id' => 1, 'name' => 'Tank', 'icon' => null]]);
        Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->put('regrowth-addon.build.priorities', $priorities, now()->addMinutes(15));

        (new BuildDataFile)->handle();

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertCount(1, $data['priorities']);
        $this->assertEquals(1, $data['priorities'][0]['id']);
    }

    public function test_it_reads_items_from_cache(): void
    {
        $items = collect([['item_id' => 100, 'priorities' => [], 'notes' => 'Test note']]);
        Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->put('regrowth-addon.build.items', $items, now()->addMinutes(15));

        (new BuildDataFile)->handle();

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertCount(1, $data['items']);
        $this->assertEquals(100, $data['items'][0]['item_id']);
    }

    public function test_it_reads_players_from_cache(): void
    {
        $players = collect([['id' => 1, 'name' => 'TestPlayer', 'attendance' => ['attended' => 5, 'total' => 10, 'percentage' => 50.0, 'first_attendance' => null]]]);
        Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->put('regrowth-addon.build.attendance', $players, now()->addMinutes(15));

        (new BuildDataFile)->handle();

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertCount(1, $data['players']);
        $this->assertEquals('TestPlayer', $data['players'][0]['name']);
    }

    public function test_it_reads_councillors_from_cache(): void
    {
        $councillors = collect([['id' => 1, 'name' => 'Councillor', 'rank' => 'Officer']]);
        Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->put('regrowth-addon.build.councillors', $councillors, now()->addMinutes(15));

        (new BuildDataFile)->handle();

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertCount(1, $data['councillors']);
        $this->assertEquals('Councillor', $data['councillors'][0]['name']);
    }

    public function test_it_defaults_to_empty_sections_on_cache_miss(): void
    {
        (new BuildDataFile)->handle();

        $data = json_decode(Storage::disk('local')->get('addon/export.json'), true);
        $this->assertEmpty($data['priorities']);
        $this->assertEmpty($data['items']);
        $this->assertEmpty($data['players']);
        $this->assertEmpty($data['councillors']);
    }

    // ==========================================
    // Cache Cleanup Tests
    // ==========================================

    public function test_it_flushes_build_cache_after_writing_file(): void
    {
        Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->put('regrowth-addon.build.priorities', collect([['id' => 1]]), now()->addMinutes(15));
        Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->put('regrowth-addon.build.items', collect(), now()->addMinutes(15));

        (new BuildDataFile)->handle();

        $this->assertNull(Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.priorities'));
        $this->assertNull(Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items'));
    }

    // ==========================================
    // Failure Handling Tests
    // ==========================================

    public function test_it_logs_error_on_failure(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'BuildDataFile job failed.'
                    && isset($context['error']);
            });

        $job = new BuildDataFile;
        $job->failed(new \RuntimeException('Test failure'));
    }
}
