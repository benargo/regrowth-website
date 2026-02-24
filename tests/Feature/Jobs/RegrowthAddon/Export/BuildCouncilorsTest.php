<?php

namespace Tests\Feature\Jobs\RegrowthAddon\Export;

use App\Jobs\RegrowthAddon\Export\BuildCouncillors;
use App\Models\Character;
use App\Models\GuildRank;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BuildCouncilorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
    }

    // ==========================================
    // Job Contract Tests
    // ==========================================

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new BuildCouncillors);
    }

    public function test_it_uses_batchable_trait(): void
    {
        $this->assertContains(Batchable::class, class_uses_recursive(BuildCouncillors::class));
    }

    public function test_it_has_correct_job_tags(): void
    {
        $this->assertEquals(['regrowth-addon', 'regrowth-addon:build'], (new BuildCouncillors)->tags());
    }

    public function test_it_has_skip_if_batch_cancelled_middleware(): void
    {
        $middlewareClasses = array_map(fn ($m) => get_class($m), (new BuildCouncillors)->middleware());

        $this->assertContains(SkipIfBatchCancelled::class, $middlewareClasses);
    }

    // ==========================================
    // Cache Tests
    // ==========================================

    public function test_it_caches_empty_collection_when_no_councillors_exist(): void
    {
        (new BuildCouncillors)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.councillors');

        $this->assertNotNull($cached);
        $this->assertEmpty($cached);
    }

    public function test_it_caches_councillors_under_correct_key_and_tags(): void
    {
        $rank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Officer']);
        Character::factory()->lootCouncillor()->create(['name' => 'Councillor1', 'rank_id' => $rank->id]);

        (new BuildCouncillors)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.councillors');

        $this->assertNotNull($cached);
        $this->assertNotEmpty($cached);
    }

    // ==========================================
    // Councillor Data Tests
    // ==========================================

    public function test_it_includes_councillors_when_they_exist(): void
    {
        $rank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Officer']);
        Character::factory()->lootCouncillor()->create(['name' => 'Councillor1', 'rank_id' => $rank->id]);
        Character::factory()->lootCouncillor()->create(['name' => 'Councillor2', 'rank_id' => $rank->id]);

        (new BuildCouncillors)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.councillors');

        $this->assertCount(2, $cached);
    }

    public function test_it_includes_councillor_id_name_and_rank(): void
    {
        $rank = GuildRank::factory()->doesNotCountAttendance()->create(['name' => 'Officer']);
        $character = Character::factory()->lootCouncillor()->create(['name' => 'TestCouncillor', 'rank_id' => $rank->id]);

        (new BuildCouncillors)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.councillors');

        $councillor = collect($cached)->firstWhere('id', $character->id);
        $this->assertNotNull($councillor);
        $this->assertEquals('TestCouncillor', $councillor['name']);
        $this->assertEquals('Officer', $councillor['rank']);
    }

    public function test_it_includes_null_rank_for_councillor_without_rank(): void
    {
        Character::factory()->lootCouncillor()->create(['name' => 'UnrankedCouncillor', 'rank_id' => null]);

        (new BuildCouncillors)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.councillors');

        $councillor = collect($cached)->firstWhere('name', 'UnrankedCouncillor');
        $this->assertNull($councillor['rank']);
    }

    public function test_it_orders_councillors_by_name(): void
    {
        Character::factory()->lootCouncillor()->create(['name' => 'Zara']);
        Character::factory()->lootCouncillor()->create(['name' => 'Alice']);
        Character::factory()->lootCouncillor()->create(['name' => 'Milo']);

        (new BuildCouncillors)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.councillors');

        $names = collect($cached)->pluck('name')->toArray();
        $this->assertEquals(['Alice', 'Milo', 'Zara'], $names);
    }

    public function test_it_excludes_non_councillor_characters(): void
    {
        Character::factory()->lootCouncillor()->create(['name' => 'IsCouncillor']);
        Character::factory()->create(['name' => 'NotCouncillor']);

        (new BuildCouncillors)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.councillors');

        $names = collect($cached)->pluck('name')->toArray();
        $this->assertContains('IsCouncillor', $names);
        $this->assertNotContains('NotCouncillor', $names);
    }

    // ==========================================
    // Failure Handling Tests
    // ==========================================

    public function test_it_logs_error_on_failure(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'BuildCouncillors job failed.'
                    && isset($context['error']);
            });

        $job = new BuildCouncillors;
        $job->failed(new \RuntimeException('Test failure'));
    }
}
