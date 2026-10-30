<?php

namespace Tests\Feature\Raiding;

use App\Actions\Handbook\TrashChapter;
use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Models\DiscordChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class HandbookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $disk = Storage::disk('handbook');
        $disk->put('introduction.md', "# Introduction\n\nWelcome.");
        $disk->put('loot.md', "# Loot\n\nLoot rules.");
        $disk->put('tactics.md', "# Tactics\n\nBe good.");
        $disk->put('chapters.json', json_encode(['loot', 'tactics']));
    }

    #[Test]
    #[Group('happy-path')]
    public function index_shows_the_introduction(): void
    {
        $this->actingAs($this->member())
            ->get(route('raiding.handbook.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Raiding/Handbook/Show')
                ->where('chapter.slug', 'introduction')
                ->where('chapters.0.slug', 'introduction')
                ->where('chapters.1.slug', 'loot')
                ->where('chapters.2.slug', 'tactics')
                ->where('previous', null)
                ->where('next.slug', 'loot')
            );
    }

    // ==================== show ====================

    #[Test]
    #[Group('happy-path')]
    public function show_shows_a_chapter_with_its_neighbours(): void
    {
        $this->actingAs($this->member())
            ->get(route('raiding.handbook.show', 'loot'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Raiding/Handbook/Show')
                ->where('chapter.slug', 'loot')
                ->where('previous.slug', 'introduction')
                ->where('next.slug', 'tactics')
            );
    }

    #[Test]
    public function show_has_no_next_for_the_last_chapter(): void
    {
        $this->actingAs($this->member())
            ->get(route('raiding.handbook.show', 'tactics'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('chapter.slug', 'tactics')
                ->where('previous.slug', 'loot')
                ->where('next', null)
            );
    }

    #[Test]
    public function show_places_an_unlisted_chapter_after_the_listed_chapters(): void
    {
        Storage::disk('handbook')->put('extras.md', "# Extras\n\nMore.");

        $this->actingAs($this->member())
            ->get(route('raiding.handbook.show', 'extras'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('chapter.slug', 'extras')
                ->where('previous.slug', 'tactics')
                ->where('next', null)
            );
    }

    #[Test]
    public function show_returns_not_found_for_an_uppercase_slug(): void
    {
        $this->actingAs($this->member())
            ->get('/raiding/handbook/Loot')
            ->assertNotFound();
    }

    #[Test]
    public function show_redirects_the_introduction_slug_to_the_index(): void
    {
        $this->actingAs($this->member())
            ->get(route('raiding.handbook.show', 'introduction'))
            ->assertRedirect(route('raiding.handbook.index'));
    }

    #[Test]
    public function show_returns_not_found_for_an_unknown_slug(): void
    {
        $this->actingAs($this->member())
            ->get(route('raiding.handbook.show', 'nope'))
            ->assertNotFound();
    }

    #[Test]
    public function show_returns_not_found_for_a_trashed_chapter(): void
    {
        TrashChapter::run('tactics');
        Cache::flush();

        $this->actingAs($this->member())
            ->get(route('raiding.handbook.show', 'tactics'))
            ->assertNotFound();
    }

    // ==================== mentions ====================

    #[Test]
    public function it_includes_mentions_from_the_chapters(): void
    {
        $mentioned = DiscordChannel::factory()->create(['name' => 'tactics-room']);
        $other = DiscordChannel::factory()->create(['name' => 'off-topic']);
        Storage::disk('handbook')->put('loot.md', "# Loot\n\nSee <#{$mentioned->id}>.");
        Cache::flush();

        $response = $this->actingAs($this->member())
            ->get(route('raiding.handbook.index'))
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['mentions']['channels'])->pluck('id');

        $this->assertTrue($ids->contains($mentioned->id));
        $this->assertFalse($ids->contains($other->id));
    }

    // ==================== middleware ====================

    #[Test]
    #[Group('authorization')]
    public function it_redirects_guests_to_login(): void
    {
        $this->get(route('raiding.handbook.index'))->assertRedirect(route('login'));
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_users_without_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('raiding.handbook.index'))
            ->assertForbidden();
    }

    #[Test]
    public function it_does_not_remember_the_game_version(): void
    {
        $this->actingAs($this->member())
            ->get(route('raiding.handbook.index'))
            ->assertOk()
            ->assertSessionMissing(SharesCurrentGameVersionSession::SESSION_KEY);
    }

    // ==================== helpers ====================

    private function member(): User
    {
        return User::factory()->withPermissions('view-handbook')->create();
    }
}
