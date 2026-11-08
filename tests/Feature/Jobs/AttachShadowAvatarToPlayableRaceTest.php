<?php

namespace Tests\Feature\Jobs;

use App\Contracts\HasShadowAvatars;
use App\Enums\Gender;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchShadowAvatarRequest;
use App\Jobs\AttachShadowAvatarToPlayableRace;
use App\Models\PlayableRace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('blizzard-integration')]
#[Group('media')]
class AttachShadowAvatarToPlayableRaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    // ==================== job contract ====================

    #[Group('contract')]
    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new AttachShadowAvatarToPlayableRace(6, Gender::MALE));
    }

    #[Group('contract')]
    #[Test]
    public function it_has_three_total_attempts_with_a_five_minute_backoff(): void
    {
        $reflection = new ReflectionClass(AttachShadowAvatarToPlayableRace::class);
        $triesAttributes = $reflection->getAttributes(Tries::class);
        $backoffAttributes = $reflection->getAttributes(Backoff::class);

        $this->assertCount(1, $triesAttributes);
        $this->assertSame(3, $triesAttributes[0]->newInstance()->tries);
        $this->assertCount(1, $backoffAttributes);
        $this->assertSame([300, 300], $backoffAttributes[0]->newInstance()->backoff);
    }

    #[Group('contract')]
    #[Test]
    public function it_has_the_correct_tags(): void
    {
        $this->assertSame(
            ['blizzard', 'playable-race:6'],
            (new AttachShadowAvatarToPlayableRace(6, Gender::MALE))->tags(),
        );
    }

    #[Group('contract')]
    #[Test]
    public function it_scopes_the_overlap_lock_to_the_race_and_gender(): void
    {
        /** @var WithoutOverlapping $middleware */
        $middleware = (new AttachShadowAvatarToPlayableRace(6, Gender::FEMALE))->middleware()[0];

        $this->assertInstanceOf(WithoutOverlapping::class, $middleware);
        $this->assertSame('shadow-avatar:6-1', $middleware->key);
        $this->assertSame(60, $middleware->releaseAfter);
    }

    #[Group('contract')]
    #[Test]
    public function it_queues_only_one_job_per_race_and_gender_at_a_time(): void
    {
        Queue::fake();

        AttachShadowAvatarToPlayableRace::dispatch(6, Gender::MALE);
        AttachShadowAvatarToPlayableRace::dispatch(6, Gender::MALE);
        AttachShadowAvatarToPlayableRace::dispatch(6, Gender::FEMALE);

        Queue::assertPushed(AttachShadowAvatarToPlayableRace::class, 2);
    }

    // ==================== handle ====================

    #[Group('happy-path')]
    #[Test]
    public function it_attaches_the_shadow_avatar_to_the_race_at_the_mirrored_path(): void
    {
        $playableRace = PlayableRace::factory()->create(['id' => 6]);
        Saloon::fake([FetchShadowAvatarRequest::class => MockResponse::make(body: 'BINARY', status: 200, headers: ['Content-Type' => 'image/jpeg'])]);

        (new AttachShadowAvatarToPlayableRace(6, Gender::MALE))->handle(app(RenderConnector::class));

        $media = $playableRace->fresh()->getFirstMedia(HasShadowAvatars::MEDIA_COLLECTION);
        $this->assertSame('6-0.jpg', $media->file_name);
        $this->assertSame(0, $media->getCustomProperty('gender'));
        $this->assertSame('blizzard-cdn/shadow/avatar/6-0.jpg', $media->getPathRelativeToRoot());
        Storage::disk('s3')->assertExists('blizzard-cdn/shadow/avatar/6-0.jpg');
    }

    #[Group('happy-path')]
    #[Test]
    public function it_keeps_one_shadow_avatar_per_gender(): void
    {
        $playableRace = PlayableRace::factory()->create(['id' => 6]);
        Saloon::fake([FetchShadowAvatarRequest::class => MockResponse::make(body: 'BINARY', status: 200, headers: ['Content-Type' => 'image/jpeg'])]);

        (new AttachShadowAvatarToPlayableRace(6, Gender::MALE))->handle(app(RenderConnector::class));
        (new AttachShadowAvatarToPlayableRace(6, Gender::FEMALE))->handle(app(RenderConnector::class));

        $this->assertSame(
            ['6-0.jpg', '6-1.jpg'],
            $playableRace->fresh()->getMedia(HasShadowAvatars::MEDIA_COLLECTION)->pluck('file_name')->sort()->values()->all(),
        );
    }

    #[Group('happy-path')]
    #[Test]
    public function it_skips_the_request_when_the_gender_is_already_stored(): void
    {
        PlayableRace::factory()->create(['id' => 6]);
        Saloon::fake([FetchShadowAvatarRequest::class => MockResponse::make(body: 'BINARY', status: 200, headers: ['Content-Type' => 'image/jpeg'])]);
        $job = new AttachShadowAvatarToPlayableRace(6, Gender::MALE);

        $job->handle(app(RenderConnector::class));
        $job->handle(app(RenderConnector::class));

        Saloon::assertSentCount(1);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function errorStatuses(): array
    {
        return [
            'forbidden' => [403],
            'not found' => [404],
            'server error' => [500],
            'service unavailable' => [503],
        ];
    }

    #[Group('error-handling')]
    #[Test]
    #[DataProvider('errorStatuses')]
    public function it_logs_and_finishes_without_failing_or_retrying_when_the_cdn_returns_an_error_status(int $status): void
    {
        Log::spy();
        $playableRace = PlayableRace::factory()->create(['id' => 999]);
        Saloon::fake([FetchShadowAvatarRequest::class => MockResponse::make(body: '<Error/>', status: $status, headers: ['Content-Type' => 'application/xml'])]);
        $job = (new AttachShadowAvatarToPlayableRace(999, Gender::MALE))->withFakeQueueInteractions();

        $job->handle(app(RenderConnector::class));

        $job->assertNotFailed();
        $job->assertNotReleased();
        $this->assertFalse($playableRace->fresh()->hasMedia(HasShadowAvatars::MEDIA_COLLECTION));
        Log::shouldHaveReceived('warning')
            ->with('Blizzard shadow avatar unavailable.', ['playable_race_id' => 999, 'gender' => 0, 'status' => $status])
            ->once();
    }

    #[Group('error-handling')]
    #[Test]
    public function it_fails_without_storing_media_when_the_response_body_is_empty(): void
    {
        $playableRace = PlayableRace::factory()->create(['id' => 6]);
        Saloon::fake([FetchShadowAvatarRequest::class => MockResponse::make(body: '', status: 200, headers: ['Content-Type' => 'image/jpeg'])]);
        $job = (new AttachShadowAvatarToPlayableRace(6, Gender::MALE))->withFakeQueueInteractions();

        $job->handle(app(RenderConnector::class));

        $job->assertFailed();
        $this->assertFalse($playableRace->fresh()->hasMedia(HasShadowAvatars::MEDIA_COLLECTION));
    }

    #[Group('error-handling')]
    #[Test]
    public function it_fails_without_storing_media_when_the_response_is_not_an_image(): void
    {
        $playableRace = PlayableRace::factory()->create(['id' => 6]);
        Saloon::fake([FetchShadowAvatarRequest::class => MockResponse::make(body: '<html></html>', status: 200, headers: ['Content-Type' => 'text/html'])]);
        $job = (new AttachShadowAvatarToPlayableRace(6, Gender::MALE))->withFakeQueueInteractions();

        $job->handle(app(RenderConnector::class));

        $job->assertFailed();
        $this->assertFalse($playableRace->fresh()->hasMedia(HasShadowAvatars::MEDIA_COLLECTION));
    }
}
