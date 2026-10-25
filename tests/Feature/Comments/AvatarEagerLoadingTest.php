<?php

namespace Tests\Feature\Comments;

use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('comments')]
#[Group('account')]
#[Group('laravel-media-library')]
class AvatarEagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    #[Test]
    public function it_loads_author_and_reactor_avatars_without_n_plus_one(): void
    {
        $version = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $item = Item::factory()->fromBoss()->withName('Test Item')->create(['game_version_id' => $version->id]);

        $seed = function (int $count) use ($item): void {
            for ($i = 0; $i < $count; $i++) {
                $comment = Comment::factory()->create([
                    'commentable_id' => (string) $item->id,
                    'commentable_type' => Item::class,
                    'user_id' => $this->userWithAvatar()->id,
                ]);
                CommentReaction::factory()->create(['comment_id' => $comment->id, 'user_id' => $this->userWithAvatar()->id]);
            }
        };
        $request = fn () => $this->get(route('loot.comments'))->assertOk();

        $seed(3);
        $three = $this->mediaQueryCount($request);
        $seed(3);
        $six = $this->mediaQueryCount($request);

        $this->assertGreaterThan(0, $three);
        $this->assertSame($three, $six);
    }

    #[Test]
    public function it_loads_linked_report_creator_avatars_without_n_plus_one(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $viewer = User::factory()->create();

        $link = function (int $count) use ($report): void {
            for ($i = 0; $i < $count; $i++) {
                $other = Report::factory()->withoutGuildTag()->create();
                $report->linkedReports()->attach($other->id, ['created_by' => $this->userWithAvatar()->id]);
            }
        };
        $request = function () use ($report, $viewer): void {
            $this->actingAs($viewer->fresh())->get(route('raiding.reports.show', $report))
                ->assertInertia(fn ($page) => $page->reloadOnly('impactedReports', fn ($reload) => $reload->has('impactedReports.data')));
        };

        $link(3);
        $three = $this->mediaQueryCount($request);
        $link(3);
        $six = $this->mediaQueryCount($request);

        $this->assertGreaterThan(0, $three);
        $this->assertSame($three, $six);
    }

    /**
     * Create a user with a stored guild avatar.
     */
    private function userWithAvatar(): User
    {
        $user = User::factory()->create(['guild_avatar' => 'g']);
        $user->addMediaFromString(UploadedFile::fake()->image('a.png', 64, 64)->getContent())
            ->usingFileName('a.png')->toMediaCollection('discord_guild_avatar');

        return $user;
    }

    /**
     * Count the queries made while handling the request that load User media.
     */
    private function mediaQueryCount(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return collect($queries)->filter(fn (array $q): bool => str_contains($q['query'], 'from `media`') && in_array(User::class, $q['bindings'], true))->count();
    }
}
