<?php

namespace Tests\Feature\Jobs\RaidHelper;

use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use App\Http\Integrations\RaidHelper\Requests\GetCompositionRequest;
use App\Jobs\RaidHelper\FetchComposition;
use App\Jobs\RaidHelper\SyncComposition;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Saloon\RateLimitPlugin\Limit;
use Tests\TestCase;

#[Group('raiding')]
#[Group('raidhelper-integration')]
class FetchCompositionTest extends TestCase
{
    use RefreshDatabase;

    private RaidHelperConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connector = new RaidHelperConnector(token: 'test-token', serverId: '111222333444555666');
        $this->app->instance(RaidHelperConnector::class, $this->connector);
    }

    #[Test]
    public function it_dispatches_sync_composition_when_a_comp_exists(): void
    {
        Queue::fake();

        $event = Event::factory()->create(['raid_helper_event_id' => '999000000000000001']);

        Saloon::fake([
            GetCompositionRequest::class => MockResponse::make($this->minimalCompositionPayload(), 200),
        ]);

        $job = new FetchComposition($event->id);
        $job->handle($this->connector);

        Queue::assertPushed(SyncComposition::class, function (SyncComposition $job) {
            return $job->raidHelperEventId === '999000000000000001';
        });
    }

    #[Test]
    public function it_does_not_dispatch_sync_composition_when_comp_returns_404(): void
    {
        Queue::fake();

        $event = Event::factory()->create(['raid_helper_event_id' => '999000000000000001']);

        Saloon::fake([
            GetCompositionRequest::class => MockResponse::make(
                ['reason' => 'unknown composition', 'status' => 'failed'], 404
            ),
        ]);

        $job = new FetchComposition($event->id);
        $job->handle($this->connector);

        Queue::assertNotPushed(SyncComposition::class);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_fails_without_retry_when_the_event_model_does_not_exist(): void
    {
        $this->expectException(\Throwable::class);

        Saloon::fake([]);

        $job = new FetchComposition(99999);
        $job->handle($this->connector);

        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_releases_itself_and_logs_the_tier_when_the_rate_limit_is_reached(): void
    {
        Queue::fake();

        $event = Event::factory()->create(['raid_helper_event_id' => '999000000000000001']);

        $limit = Limit::allow(10)->everySeconds(60)->name('10_per_60s');
        $limit->exceeded(releaseInSeconds: 60);

        $connector = Mockery::mock(RaidHelperConnector::class)->makePartial();
        $connector->shouldReceive('send')->andThrow(new RateLimitReachedException($limit));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'rate limit reached')
                    && $context['limit'] === 'saloon_rate_limiter:10_per_60s';
            });

        $job = Mockery::mock(FetchComposition::class, [$event->id])->makePartial();
        $job->shouldReceive('release')->once()->with(Mockery::type('int'));

        $job->handle($connector);

        Queue::assertNotPushed(SyncComposition::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalCompositionPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => '999000000000000001',
            'title' => 'Weekly Composition',
            'editPermissions' => 'managers',
            'showRoles' => true,
            'showClasses' => true,
            'groupCount' => 0,
            'slotCount' => 0,
            'groups' => [],
            'dividers' => [],
            'classes' => [],
            'slots' => [],
        ], $overrides);
    }
}
