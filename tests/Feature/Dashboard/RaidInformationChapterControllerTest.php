<?php

namespace Tests\Feature\Dashboard;

use App\Actions\RaidInformation\TrashChapter;
use App\Jobs\Addon\BuildExportFile;
use App\Jobs\Discord\AttachAvatarToUser;
use App\Jobs\RaidInformation\ResolveChapterChannels;
use App\Models\DiscordEmoji;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class RaidInformationChapterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([BuildExportFile::class, AttachAvatarToUser::class, ResolveChapterChannels::class]);
        Cache::flush();

        $disk = Storage::disk('raid-information');
        $disk->put('introduction.md', "# Introduction\n\nWelcome.");
        $disk->put('loot.md', "# Loot\n\nLoot rules.");
        $disk->put('tactics.md', "# Tactics\n\nBe good.");
        $disk->put('chapters.json', json_encode(['loot', 'tactics']));
    }

    private function manager(): User
    {
        return User::factory()->withPermissions('manage-raid-information')->create();
    }

    private function hash(string $slug): string
    {
        return sha1((string) Storage::disk('raid-information')->get("{$slug}.md"));
    }

    private function order(): array
    {
        return json_decode((string) Storage::disk('raid-information')->get('chapters.json'), true);
    }

    // ==================== index ====================

    #[Test]
    #[Group('happy-path')]
    public function index_lists_chapters_and_trashed_chapters(): void
    {
        TrashChapter::run('tactics');
        Cache::flush();

        $this->actingAs($this->manager())
            ->get(route('management.raid-information.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manage/RaidInformation/Index')
                ->where('chapters.0.slug', 'introduction')
                ->where('chapters.1.slug', 'loot')
                ->has('chapters', 2)
                ->where('trashed.0.slug', 'tactics')
                ->where('trashed.0.days_remaining', 30)
                ->has('mentions')
            );
    }

    #[Test]
    public function index_includes_the_custom_emoji_of_trashed_chapter_headings_in_the_mentions(): void
    {
        $emoji = DiscordEmoji::factory()->create(['name' => 'skull']);
        DiscordEmoji::factory()->create(['name' => 'unused']);
        Storage::disk('raid-information')->put('tactics.md', "## <:skull:{$emoji->id}> Tactics\n\nBe good.");
        TrashChapter::run('tactics');
        Cache::flush();

        $this->actingAs($this->manager())
            ->get(route('management.raid-information.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mentions.emoji', 1)
                ->where('mentions.emoji.0.id', $emoji->id)
            );
    }

    // ==================== create ====================

    #[Test]
    #[Group('happy-path')]
    public function create_renders_the_form_with_mentions(): void
    {
        $this->actingAs($this->manager())
            ->get(route('management.raid-information.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manage/RaidInformation/Create')
                ->has('mentions')
            );
    }

    // ==================== store ====================

    #[Test]
    #[Group('happy-path')]
    public function store_creates_a_chapter_and_redirects_to_edit(): void
    {
        $this->actingAs($this->manager())
            ->post(route('management.raid-information.store'), ['content' => "## Consumables\n\nBring flasks."])
            ->assertRedirect(route('management.raid-information.edit', 'consumables'))
            ->assertSessionHas('success', 'Added Consumables.');

        $this->assertTrue(Storage::disk('raid-information')->exists('consumables.md'));
        $this->assertSame(['introduction', 'loot', 'tactics', 'consumables'], $this->order());
    }

    #[Test]
    public function store_saves_line_feeds_without_surrounding_whitespace(): void
    {
        $this->actingAs($this->manager())
            ->post(route('management.raid-information.store'), ['content' => "## Consumables\r\n\r\nBring flasks.  \r\n"])
            ->assertRedirect(route('management.raid-information.edit', 'consumables'));

        $this->assertSame("## Consumables\n\nBring flasks.", Storage::disk('raid-information')->get('consumables.md'));
    }

    // ==================== store — validation ====================

    #[Test]
    #[Group('validation')]
    public function store_rejects_over_limit_content(): void
    {
        $this->actingAs($this->manager())
            ->post(route('management.raid-information.store'), ['content' => "## Long\n\n".str_repeat('a', 2100)])
            ->assertSessionHasErrors('content');

        $this->assertFalse(Storage::disk('raid-information')->exists('long.md'));
    }

    #[Test]
    #[Group('validation')]
    public function store_rejects_a_duplicate_slug(): void
    {
        $this->actingAs($this->manager())
            ->post(route('management.raid-information.store'), ['content' => "## Loot\n\nOther."])
            ->assertSessionHasErrors('content');

        $this->assertSame("# Loot\n\nLoot rules.", Storage::disk('raid-information')->get('loot.md'));
    }

    // ==================== edit ====================

    #[Test]
    #[Group('happy-path')]
    public function edit_shows_the_chapter(): void
    {
        $this->actingAs($this->manager())
            ->get(route('management.raid-information.edit', 'loot'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manage/RaidInformation/Edit')
                ->where('chapter.slug', 'loot')
                ->where('chapter.hash', $this->hash('loot'))
                ->has('mentions')
            );
    }

    #[Test]
    public function edit_returns_not_found_for_an_unknown_slug(): void
    {
        $this->actingAs($this->manager())
            ->get(route('management.raid-information.edit', 'nope'))
            ->assertNotFound();
    }

    // ==================== update ====================

    #[Test]
    #[Group('happy-path')]
    public function update_saves_the_chapter(): void
    {
        $this->actingAs($this->manager())
            ->from(route('management.raid-information.edit', 'loot'))
            ->patch(route('management.raid-information.update', 'loot'), [
                'content' => "## Loot\n\nNew rules.",
                'base_hash' => $this->hash('loot'),
            ])
            ->assertRedirect(route('management.raid-information.edit', 'loot'))
            ->assertSessionHas('success', 'Saved Loot.');

        $this->assertSame("## Loot\n\nNew rules.", Storage::disk('raid-information')->get('loot.md'));
    }

    #[Test]
    public function update_saves_a_chapter_slugged_order(): void
    {
        Storage::disk('raid-information')->put('order.md', "## Order\n\nFirst.");
        Storage::disk('raid-information')->put('chapters.json', json_encode(['loot', 'tactics', 'order']));
        Cache::flush();

        $this->actingAs($this->manager())
            ->patch('/manage/raid-information/order', [
                'content' => "## Order\n\nSecond.",
                'base_hash' => $this->hash('order'),
            ])
            ->assertSessionHas('success', 'Saved Order.');

        $this->assertSame("## Order\n\nSecond.", Storage::disk('raid-information')->get('order.md'));
    }

    #[Test]
    public function update_reports_no_changes_for_identical_content(): void
    {
        Storage::disk('raid-information')->put('loot.md', "## Loot\n\nLoot rules.");
        $path = Storage::disk('raid-information')->path('loot.md');
        touch($path, time() - 1000);
        clearstatcache();
        $mtime = filemtime($path);

        $this->actingAs($this->manager())
            ->patch(route('management.raid-information.update', 'loot'), [
                'content' => "## Loot\n\nLoot rules.",
                'base_hash' => $this->hash('loot'),
            ])
            ->assertSessionHas('success', 'No changes to save.');

        clearstatcache();
        $this->assertSame($mtime, filemtime($path));
    }

    #[Test]
    public function update_reports_no_changes_for_content_that_differs_only_by_line_endings(): void
    {
        Storage::disk('raid-information')->put('loot.md', "## Loot\n\nLoot rules.");

        $this->actingAs($this->manager())
            ->patch(route('management.raid-information.update', 'loot'), [
                'content' => "## Loot\r\n\r\nLoot rules.\r\n",
                'base_hash' => $this->hash('loot'),
            ])
            ->assertSessionHas('success', 'No changes to save.');
    }

    #[Test]
    public function update_refuses_a_stale_base_hash(): void
    {
        $this->actingAs($this->manager())
            ->patch(route('management.raid-information.update', 'loot'), [
                'content' => "## Loot\n\nNew rules.",
                'base_hash' => str_repeat('a', 40),
            ])
            ->assertSessionHasErrors('base_hash');

        $this->assertSame("# Loot\n\nLoot rules.", Storage::disk('raid-information')->get('loot.md'));
    }

    #[Test]
    public function update_returns_not_found_for_a_trashed_chapter(): void
    {
        TrashChapter::run('tactics');
        Cache::flush();

        $this->actingAs($this->manager())
            ->patch(route('management.raid-information.update', 'tactics'), [
                'content' => "## Tactics\n\nChanged.",
                'base_hash' => str_repeat('a', 40),
            ])
            ->assertNotFound();

        $disk = Storage::disk('raid-information');
        $this->assertFalse($disk->exists('tactics.md'));
        $this->assertSame("# Tactics\n\nBe good.", $disk->get('.trashed/tactics.md'));
    }

    #[Test]
    public function update_lets_the_introduction_use_a_level_one_heading(): void
    {
        $this->actingAs($this->manager())
            ->patch(route('management.raid-information.update', 'introduction'), [
                'content' => "# Introduction\n\nHello.",
                'base_hash' => $this->hash('introduction'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame("# Introduction\n\nHello.", Storage::disk('raid-information')->get('introduction.md'));
    }

    // ==================== update — validation ====================

    #[Test]
    #[Group('validation')]
    public function update_rejects_over_limit_content(): void
    {
        $path = Storage::disk('raid-information')->path('loot.md');
        touch($path, time() - 1000);
        clearstatcache();
        $mtime = filemtime($path);

        $this->actingAs($this->manager())
            ->patch(route('management.raid-information.update', 'loot'), [
                'content' => "## Loot\n\n".str_repeat('a', 2100),
                'base_hash' => $this->hash('loot'),
            ])
            ->assertSessionHasErrors('content');

        clearstatcache();
        $this->assertSame("# Loot\n\nLoot rules.", Storage::disk('raid-information')->get('loot.md'));
        $this->assertSame($mtime, filemtime($path));
    }

    #[Test]
    #[Group('validation')]
    public function update_rejects_a_level_two_heading_for_the_introduction(): void
    {
        $this->actingAs($this->manager())
            ->patch(route('management.raid-information.update', 'introduction'), [
                'content' => "## Introduction\n\nHello.",
                'base_hash' => $this->hash('introduction'),
            ])
            ->assertSessionHasErrors('content');
    }

    // ==================== destroy ====================

    #[Test]
    #[Group('happy-path')]
    public function destroy_trashes_the_chapter(): void
    {
        $this->actingAs($this->manager())
            ->delete(route('management.raid-information.destroy', 'loot'))
            ->assertRedirect(route('management.raid-information.index'))
            ->assertSessionHas('success', 'Deleted Loot. You can restore it for 30 days.');

        $disk = Storage::disk('raid-information');
        $this->assertFalse($disk->exists('loot.md'));
        $this->assertTrue($disk->exists('.trashed/loot.md'));
        $this->assertSame(['introduction', 'tactics'], $this->order());
    }

    #[Test]
    public function destroy_refuses_the_introduction(): void
    {
        $this->actingAs($this->manager())
            ->delete(route('management.raid-information.destroy', 'introduction'))
            ->assertSessionHasErrors('chapter');

        $this->assertTrue(Storage::disk('raid-information')->exists('introduction.md'));
    }

    #[Test]
    public function destroy_returns_not_found_for_an_unknown_slug(): void
    {
        $this->actingAs($this->manager())
            ->delete(route('management.raid-information.destroy', 'nope'))
            ->assertNotFound();
    }

    // ==================== restore ====================

    #[Test]
    #[Group('happy-path')]
    public function restore_restores_a_trashed_chapter(): void
    {
        TrashChapter::run('tactics');

        $this->actingAs($this->manager())
            ->post(route('management.raid-information.restore', 'tactics'))
            ->assertRedirect(route('management.raid-information.index'))
            ->assertSessionHas('success', 'Restored Tactics.');

        $disk = Storage::disk('raid-information');
        $this->assertTrue($disk->exists('tactics.md'));
        $this->assertFalse($disk->exists('.trashed/tactics.md'));
        $this->assertSame(['introduction', 'loot', 'tactics'], $this->order());
    }

    #[Test]
    public function restore_returns_not_found_for_an_unknown_slug(): void
    {
        $this->actingAs($this->manager())
            ->post(route('management.raid-information.restore', 'nope'))
            ->assertNotFound();
    }

    // ==================== reorder ====================

    #[Test]
    #[Group('happy-path')]
    public function reorder_writes_the_new_order(): void
    {
        $this->actingAs($this->manager())
            ->from(route('management.raid-information.index'))
            ->put(route('management.raid-information.reorder'), ['slugs' => ['tactics', 'loot']])
            ->assertRedirect(route('management.raid-information.index'));

        $this->assertSame(['introduction', 'tactics', 'loot'], $this->order());
    }

    #[Test]
    public function reorder_returns_without_a_flash_for_autosave(): void
    {
        $this->actingAs($this->manager())
            ->from(route('management.raid-information.index'))
            ->withHeader('X-Autosave', '1')
            ->put(route('management.raid-information.reorder'), ['slugs' => ['tactics', 'loot']])
            ->assertRedirect(route('management.raid-information.index'))
            ->assertSessionMissing('success');
    }

    #[Test]
    #[Group('validation')]
    public function reorder_rejects_a_stale_list(): void
    {
        $this->actingAs($this->manager())
            ->put(route('management.raid-information.reorder'), ['slugs' => ['tactics']])
            ->assertSessionHasErrors('slugs');

        $this->assertSame(['loot', 'tactics'], $this->order());
    }

    // ==================== authorization ====================

    #[Test]
    #[Group('authorization')]
    public function guests_are_redirected_to_login(): void
    {
        $this->get(route('management.raid-information.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    #[Group('authorization')]
    public function a_reader_without_manage_permission_is_forbidden(): void
    {
        $reader = User::factory()->withPermissions('view-raid-information')->create();

        $this->actingAs($reader)->get(route('management.raid-information.index'))->assertForbidden();
        $this->actingAs($reader)->patch(route('management.raid-information.update', 'loot'), [
            'content' => "## Loot\n\nX.",
            'base_hash' => $this->hash('loot'),
        ])->assertForbidden();
    }
}
