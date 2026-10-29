<?php

namespace Tests\Feature\Dashboard;

use App\Actions\Boss\BuildBossRoutes;
use App\Actions\Boss\UpdateBoss;
use App\Http\Middleware\SetResourceScope;
use App\Http\Resources\BossResource;
use App\Jobs\Addon\BuildExportFile;
use App\Models\Boss;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Cache\Events\CacheFlushed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\Matcher\MatcherInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Support\DashboardTestCase;

#[Group('raiding')]
class BossControllerTest extends DashboardTestCase
{
    #[Group('authorization')]
    #[Test]
    public function it_redirects_guests_to_login(): void
    {
        $response = $this->get(route('management.bosses.index'));

        $response->assertRedirect(route('login'));
    }

    // ==================== index ====================

    #[Group('authorization')]
    #[Test]
    public function it_forbids_the_index_without_edit_datasets_permission(): void
    {
        $response = $this->actingAs($this->dashboardOnlyUser())->get(route('management.bosses.index'));

        $response->assertForbidden();
    }

    #[Group('happy-path')]
    #[Test]
    public function it_lists_bosses_under_their_raid_and_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Anniversary']);
        $raid = Raid::factory()->for(Phase::factory()->for($gameVersion))->create(['name' => 'Karazhan']);
        $boss = Boss::factory()->for($raid)->create(['name' => 'Moroes', 'notes' => 'Vanish.']);

        $response = $this->actingAs($this->officer)->get(route('management.bosses.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Bosses/Index')
            ->has('groups', 1)
            ->where('groups.0.game_version.data.title', 'Anniversary')
            ->where('groups.0.raids.0.raid.data.name', 'Karazhan')
            ->where('groups.0.raids.0.bosses.data.0.name', 'Moroes')
            ->where('groups.0.raids.0.bosses.data.0.meta.has_strategy', true)
            ->where('groups.0.raids.0.bosses.data.0.links', [
                'edit' => route('management.bosses.edit', $boss),
                'destroy' => route('management.bosses.destroy', $boss),
            ])
            ->missing('groups.0.raids.0.bosses.data.0.images')
            ->where('routes', ['create' => route('management.bosses.create')])
        );
    }

    #[Test]
    public function it_lists_bosses_without_lazy_loading_media(): void
    {
        Storage::fake('s3');
        $raid = Raid::factory()->for(Phase::factory()->for(GameVersion::factory()))->create();
        Boss::factory()->count(2)->for($raid)->create(['notes' => null])
            ->each(fn (Boss $boss) => $boss->addMedia(UploadedFile::fake()->image('room.png'))->toMediaCollection());

        config(['media-library.force_lazy_loading' => false]);
        Model::preventLazyLoading();

        try {
            $response = $this->withoutExceptionHandling()->actingAs($this->officer)->get(route('management.bosses.index'));

            $response->assertInertia(fn (Assert $page) => $page
                ->where('groups.0.raids.0.bosses.data.0.meta.has_strategy', true)
                ->where('groups.0.raids.0.bosses.data.1.meta.has_strategy', true)
            );
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    // ==================== create ====================

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_create_page_with_the_raid_options(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Anniversary']);
        $raid = Raid::factory()->for(Phase::factory()->for($gameVersion))->create(['name' => 'Karazhan', 'difficulty' => 'Normal']);

        $response = $this->actingAs($this->officer)->get(route('management.bosses.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Bosses/Create')
            ->where('options.raids', [['label' => 'Anniversary', 'options' => [['value' => $raid->id, 'label' => 'Karazhan (Normal)']]]])
            ->where('raidId', null)
            ->where('from', null)
            ->where('routes', [
                'index' => route('management.bosses.index'),
                'store' => route('management.bosses.store'),
                'cancel' => route('management.bosses.index'),
            ])
        );
    }

    #[Test]
    public function it_preselects_the_raid_given_in_the_query(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->actingAs($this->officer)->get(route('management.bosses.create', ['raid' => $raid->id]));

        $response->assertInertia(fn (Assert $page) => $page->where('raidId', $raid->id));
    }

    #[Test]
    public function it_keeps_the_raid_setup_origin_and_cancels_back_to_the_bosses_step(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->actingAs($this->officer)
            ->get(route('management.bosses.create', ['raid' => $raid->id, 'from' => 'raid-setup']));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('from', 'raid-setup')
            ->where('routes.cancel', route('management.raids.setup', [$raid, 'bosses']))
        );
    }

    #[Test]
    public function it_ignores_an_unknown_origin_or_raid_in_the_query(): void
    {
        $response = $this->actingAs($this->officer)
            ->get(route('management.bosses.create', ['raid' => 999999, 'from' => 'elsewhere']));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('raidId', null)
            ->where('from', null)
        );
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_create_without_edit_datasets_permission(): void
    {
        $response = $this->actingAs($this->dashboardOnlyUser())->get(route('management.bosses.create'));

        $response->assertForbidden();
    }

    // ==================== store ====================

    #[Group('happy-path')]
    #[Test]
    public function it_stores_a_boss_at_the_end_of_its_raids_order_and_redirects_to_its_edit_page(): void
    {
        $raid = Raid::factory()->create();
        Boss::factory()->for($raid)->create(['name' => 'Attumen the Huntsman']);

        $response = $this->actingAs($this->officer)->post(route('management.bosses.store'), [
            'raid_id' => $raid->id,
            'name' => 'Moroes',
            'from' => '',
        ]);

        $boss = Boss::firstWhere('name', 'Moroes');
        $response->assertRedirect(route('management.bosses.edit', $boss));
        $response->assertSessionHas('success', 'Added Moroes.');
        $this->assertSame($raid->id, $boss->raid_id);
        $this->assertSame(2, $boss->sort_order);
    }

    #[Test]
    public function it_returns_to_the_raids_bosses_step_when_opened_from_there(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('management.bosses.store'), [
            'raid_id' => $raid->id,
            'name' => 'Moroes',
            'from' => BuildBossRoutes::FROM_RAID_SETUP,
        ]);

        $response->assertRedirect(route('management.raids.setup', [$raid, 'bosses']));
    }

    #[Test]
    public function it_returns_to_the_raids_edit_page_when_opened_from_there(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('management.bosses.store'), [
            'raid_id' => $raid->id,
            'name' => 'Moroes',
            'from' => BuildBossRoutes::FROM_RAID_EDIT,
        ]);

        $response->assertRedirect(route('management.raids.edit', $raid));
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_unknown_origin(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->actingAs($this->officer)
            ->from(route('management.bosses.create'))
            ->post(route('management.bosses.store'), ['raid_id' => $raid->id, 'name' => 'Moroes', 'from' => 'elsewhere']);

        $response->assertSessionHasErrors(['from' => "Open this form from the bosses list or a raid's Bosses step."]);
        $this->assertDatabaseCount('bosses', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_requires_a_name_and_a_raid(): void
    {
        $response = $this->actingAs($this->officer)
            ->from(route('management.bosses.create'))
            ->post(route('management.bosses.store'), ['raid_id' => '', 'name' => '']);

        $response->assertSessionHasErrors([
            'name' => 'Enter the boss name.',
            'raid_id' => 'Choose the raid this boss is in.',
        ]);
        $this->assertDatabaseCount('bosses', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_unknown_raid(): void
    {
        $response = $this->actingAs($this->officer)
            ->from(route('management.bosses.create'))
            ->post(route('management.bosses.store'), ['raid_id' => 999999, 'name' => 'Moroes']);

        $response->assertSessionHasErrors(['raid_id' => 'Choose one of the listed raids.']);
        $this->assertDatabaseCount('bosses', 0);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_name_the_raid_already_uses(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        Boss::factory()->for($raid)->create(['name' => 'Moroes']);

        $response = $this->actingAs($this->officer)
            ->from(route('management.bosses.create'))
            ->post(route('management.bosses.store'), ['raid_id' => $raid->id, 'name' => 'Moroes']);

        $response->assertSessionHasErrors(['name' => 'Karazhan already has a boss called Moroes.']);
        $this->assertDatabaseCount('bosses', 1);
    }

    #[Test]
    public function it_allows_a_name_another_raid_uses(): void
    {
        Boss::factory()->for(Raid::factory())->create(['name' => 'Moroes']);
        $raid = Raid::factory()->create();

        $response = $this->actingAs($this->officer)
            ->post(route('management.bosses.store'), ['raid_id' => $raid->id, 'name' => 'Moroes']);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bosses', 2);
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_store_without_edit_datasets_permission(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->actingAs($this->dashboardOnlyUser())
            ->post(route('management.bosses.store'), ['raid_id' => $raid->id, 'name' => 'Moroes']);

        $response->assertForbidden();
        $this->assertDatabaseCount('bosses', 0);
    }

    // ==================== edit ====================

    #[Group('happy-path')]
    #[Test]
    public function it_renders_the_edit_page_with_the_boss_its_raid_and_images(): void
    {
        Storage::fake('s3');
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        $boss = Boss::factory()->for($raid)->create(['name' => 'Moroes', 'notes' => 'Vanish.']);
        $media = $boss->addMedia(UploadedFile::fake()->image('room.png'))->toMediaCollection();

        $response = $this->actingAs($this->officer)->get($this->editUrl($boss));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Bosses/Edit')
            ->where('boss.data.name', 'Moroes')
            ->where('boss.data.notes', 'Vanish.')
            ->where('boss.data.raid.id', $raid->id)
            ->where('boss.data.raid.name', 'Karazhan')
            ->missing('boss.data.raid_id')
            ->missing('boss.data.raid.slug')
            ->where('boss.data.images', [['id' => $media->id, 'url' => $media->getUrl()]])
            ->missing('boss.data.strategy_images')
            ->where('routes', [
                'index' => route('management.bosses.index'),
                'update' => route('management.bosses.update', $boss),
                'raid' => route('management.raids.edit', $raid),
            ])
            ->where('canEdit', true)
        );
    }

    #[Test]
    #[TestWith(['management.bosses.index'])]
    #[TestWith(['management.bosses.edit'])]
    public function the_management_pages_scope_the_boss_resource_for_the_request(string $routeName): void
    {
        $this->assertContains(
            SetResourceScope::class.':'.BossResource::class.',management',
            Route::getRoutes()->getByName($routeName)->gatherMiddleware(),
        );
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_edit_without_edit_datasets_permission(): void
    {
        $response = $this->actingAs($this->dashboardOnlyUser())->get($this->editUrl(Boss::factory()->create()));

        $response->assertForbidden();
    }

    // ==================== edit lock ====================

    #[Test]
    public function it_shows_the_edit_page_read_only_to_a_second_officer_while_the_lock_is_held(): void
    {
        $boss = Boss::factory()->create();
        $holder = User::factory()->officer()->create();
        $boss->acquireEditLock($holder);

        $response = $this->actingAs($this->officer)->get($this->editUrl($boss));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('editor', $holder->display_name)
        );
    }

    #[Test]
    public function it_rejects_an_update_while_another_officer_holds_the_edit_lock(): void
    {
        $boss = Boss::factory()->create();
        $boss->acquireEditLock(User::factory()->officer()->create());
        UpdateBoss::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), ['notes' => 'Mine']);

        $response->assertSessionHasErrors(['edit_lock' => 'Someone else is editing this boss. Your change was not saved.']);
    }

    // ==================== update ====================

    #[Group('happy-path')]
    #[Test]
    public function it_updates_a_boss_and_redirects_back(): void
    {
        $boss = Boss::factory()->create(['name' => 'Moroes']);
        $this->expectUpdate($boss, ['notes' => 'Vanish.']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), ['notes' => 'Vanish.']);

        $response->assertRedirect($this->editUrl($boss));
        $response->assertSessionHas('success', 'Saved changes to Moroes.');
    }

    #[Test]
    public function it_flushes_the_raiding_cache_once_for_an_update_with_several_images(): void
    {
        Storage::fake('s3');
        $boss = Boss::factory()->create(['name' => 'Moroes']);
        Cache::tags(['raiding', 'events'])->put('canary', true, 60);
        $flushes = 0;
        EventFacade::listen(CacheFlushed::class, function () use (&$flushes): void {
            $flushes++;
        });

        $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), [
                'name' => 'Moroes the Butler',
                'images' => [
                    UploadedFile::fake()->image('one.png'),
                    UploadedFile::fake()->image('two.png'),
                    UploadedFile::fake()->image('three.png'),
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $flushes);
        $this->assertFalse(Cache::tags(['raiding', 'events'])->has('canary'));
        $this->assertCount(3, $boss->getMedia());
    }

    #[Test]
    public function it_saves_an_autosave_without_flashing_a_message(): void
    {
        $boss = Boss::factory()->create();
        $this->expectUpdate($boss, ['notes' => 'Vanish.']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->withHeader('X-Autosave', '1')
            ->patch(route('management.bosses.update', $boss), ['notes' => 'Vanish.']);

        $response->assertRedirect($this->editUrl($boss));
        $response->assertSessionMissing('success');
    }

    #[Test]
    public function it_passes_cleared_notes_as_null(): void
    {
        $boss = Boss::factory()->create(['notes' => 'Vanish.']);
        $this->expectUpdate($boss, ['notes' => null]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), ['notes' => '']);

        $response->assertSessionHasNoErrors();
    }

    #[Test]
    public function it_allows_an_update_that_keeps_the_same_name(): void
    {
        $boss = Boss::factory()->create(['name' => 'Moroes']);
        $this->expectUpdate($boss, ['name' => 'Moroes']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), ['name' => 'Moroes']);

        $response->assertSessionHasNoErrors();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_name_another_boss_in_the_raid_uses(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        Boss::factory()->for($raid)->create(['name' => 'Moroes']);
        $boss = Boss::factory()->for($raid)->create(['name' => 'Attumen the Huntsman']);
        UpdateBoss::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), ['name' => 'Moroes']);

        $response->assertSessionHasErrors(['name' => 'Karazhan already has a boss called Moroes.']);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_a_name_sent_as_null(): void
    {
        $boss = Boss::factory()->create();
        UpdateBoss::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), ['name' => null]);

        $response->assertInvalid(['name' => 'Enter the boss name.']);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_image_larger_than_2_mb(): void
    {
        $boss = Boss::factory()->create();
        UpdateBoss::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), ['images' => [UploadedFile::fake()->image('big.png')->size(2049)]]);

        $response->assertInvalid(['images.0' => 'Each image must be 2 MB or smaller.']);
    }

    #[Group('validation')]
    #[Test]
    public function it_prohibits_moving_a_boss_to_another_raid(): void
    {
        $boss = Boss::factory()->create();
        $otherRaid = Raid::factory()->create();
        UpdateBoss::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), ['raid_id' => $otherRaid->id]);

        $response->assertSessionHasErrors(['raid_id' => "A boss can't move to another raid. Delete it and add it to the other raid instead."]);
        $this->assertNotSame($otherRaid->id, $boss->fresh()->raid_id);
    }

    #[Test]
    public function it_accepts_an_order_of_the_images_left_after_removals(): void
    {
        Storage::fake('s3');
        $boss = Boss::factory()->create();
        [$first, $second, $third] = $this->addImages($boss, 3);
        $this->expectUpdate($boss, ['removed_image_ids' => [$first->id], 'image_ids' => [$third->id, $second->id]]);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), [
                'removed_image_ids' => [$first->id],
                'image_ids' => [$third->id, $second->id],
            ]);

        $response->assertSessionHasNoErrors();
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_removing_another_bosss_image(): void
    {
        Storage::fake('s3');
        $boss = Boss::factory()->create();
        [$otherBossImage] = $this->addImages(Boss::factory()->create(), 1);
        UpdateBoss::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), ['removed_image_ids' => [$otherBossImage->id]]);

        $response->assertSessionHasErrors(['removed_image_ids.0' => "Remove this boss's own images."]);
        $this->assertModelExists($otherBossImage);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_image_order_that_is_not_exactly_the_bosss_images(): void
    {
        Storage::fake('s3');
        $boss = Boss::factory()->create();
        [$first] = $this->addImages($boss, 2);
        [$otherBossImage] = $this->addImages(Boss::factory()->create(), 1);
        UpdateBoss::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), ['image_ids' => [$first->id, $otherBossImage->id]]);

        $response->assertSessionHasErrors(['image_ids' => "Reorder this boss's own images."]);
    }

    #[Group('validation')]
    #[Test]
    public function it_rejects_an_upload_that_is_not_an_allowed_image(): void
    {
        $boss = Boss::factory()->create();
        UpdateBoss::shouldNotRun();

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), ['images' => [UploadedFile::fake()->image('strategy.gif')]]);

        $response->assertSessionHasErrors(['images.0' => 'Upload JPEG, PNG or WEBP images.']);
    }

    #[Test]
    public function it_accepts_an_update_from_the_edit_lock_holder(): void
    {
        $boss = Boss::factory()->create();
        $boss->acquireEditLock($this->officer);
        $this->expectUpdate($boss, ['notes' => 'Vanish.']);

        $response = $this->actingAs($this->officer)
            ->from($this->editUrl($boss))
            ->patch(route('management.bosses.update', $boss), ['notes' => 'Vanish.']);

        $response->assertSessionHasNoErrors();
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_update_without_edit_datasets_permission(): void
    {
        $boss = Boss::factory()->create(['notes' => 'Keep me.']);

        $response = $this->actingAs($this->dashboardOnlyUser())
            ->patch(route('management.bosses.update', $boss), ['notes' => 'Changed.']);

        $response->assertForbidden();
        $this->assertSame('Keep me.', $boss->fresh()->notes);
    }

    // ==================== destroy ====================

    #[Group('happy-path')]
    #[Test]
    public function it_deletes_a_boss_that_is_not_in_use(): void
    {
        $boss = Boss::factory()->create(['name' => 'Moroes']);

        $response = $this->actingAs($this->officer)->delete(route('management.bosses.destroy', $boss));

        $response->assertRedirect(route('management.bosses.index'));
        $response->assertSessionHas('success', 'Deleted Moroes.');
        $this->assertModelMissing($boss);
    }

    #[Test]
    public function it_deletes_a_boss_linked_only_to_past_events_and_templates_with_their_rows(): void
    {
        $this->freezeTime();
        $boss = Boss::factory()->create(['name' => 'Moroes']);
        $past = $this->eventInHours(-48, -45);
        $template = Event::factory()->template()->create(['start_time' => null, 'end_time' => null]);
        $past->bosses()->attach($boss->id, ['sort_order' => 1]);
        $template->bosses()->attach($boss->id, ['sort_order' => 1]);
        $assignment = EventAssignment::factory()->forBoss($boss)->create(['event_id' => $past->id]);
        $group = EventAssignmentGroup::factory()->forBoss($boss)->create(['event_id' => $past->id]);

        $response = $this->actingAs($this->officer)->delete(route('management.bosses.destroy', $boss));

        $response->assertSessionHas('success', 'Deleted Moroes.');
        $this->assertModelMissing($boss);
        $this->assertDatabaseMissing('pivot_events_bosses', ['boss_id' => $boss->id]);
        $this->assertModelMissing($assignment);
        $this->assertModelMissing($group);
        $this->assertModelExists($past);
        $this->assertModelExists($template);
    }

    #[Test]
    public function it_refuses_to_delete_a_boss_with_items(): void
    {
        Bus::fake([BuildExportFile::class]);
        $boss = Boss::factory()->create(['name' => 'Moroes']);
        Item::factory()->fromBoss($boss)->create();

        $response = $this->actingAs($this->officer)
            ->from(route('management.bosses.index'))
            ->delete(route('management.bosses.destroy', $boss));

        $response->assertRedirect(route('management.bosses.index'));
        $response->assertSessionHas('error', 'Moroes is still linked to other records and cannot be deleted.');
        $this->assertModelExists($boss);
    }

    #[Test]
    #[TestWith([24, 27], 'upcoming')]
    #[TestWith([-1, 2], 'in progress')]
    public function it_refuses_to_delete_a_boss_linked_to_an_upcoming_or_in_progress_event(int $startsInHours, int $endsInHours): void
    {
        $this->freezeTime();
        $boss = Boss::factory()->create(['name' => 'Moroes']);
        $this->eventInHours($startsInHours, $endsInHours)->bosses()->attach($boss->id, ['sort_order' => 1]);

        $response = $this->actingAs($this->officer)
            ->from(route('management.bosses.index'))
            ->delete(route('management.bosses.destroy', $boss));

        $response->assertSessionHas('error', 'Moroes is still linked to other records and cannot be deleted.');
        $this->assertModelExists($boss);
        $this->assertDatabaseHas('pivot_events_bosses', ['boss_id' => $boss->id]);
    }

    #[Test]
    public function it_refuses_to_delete_a_boss_with_an_upcoming_assignment(): void
    {
        $this->freezeTime();
        $boss = Boss::factory()->create(['name' => 'Moroes']);
        EventAssignment::factory()->forBoss($boss)->create(['event_id' => $this->eventInHours(24, 27)->id]);

        $response = $this->actingAs($this->officer)
            ->from(route('management.bosses.index'))
            ->delete(route('management.bosses.destroy', $boss));

        $response->assertSessionHas('error', 'Moroes is still linked to other records and cannot be deleted.');
        $this->assertModelExists($boss);
    }

    #[Test]
    public function it_refuses_to_delete_a_boss_with_an_upcoming_assignment_group(): void
    {
        $this->freezeTime();
        $boss = Boss::factory()->create(['name' => 'Moroes']);
        EventAssignmentGroup::factory()->forBoss($boss)->create(['event_id' => $this->eventInHours(24, 27)->id]);

        $response = $this->actingAs($this->officer)
            ->from(route('management.bosses.index'))
            ->delete(route('management.bosses.destroy', $boss));

        $response->assertSessionHas('error', 'Moroes is still linked to other records and cannot be deleted.');
        $this->assertModelExists($boss);
    }

    #[Test]
    public function it_refuses_to_delete_a_boss_another_officer_is_editing(): void
    {
        $boss = Boss::factory()->create(['name' => 'Moroes']);
        $boss->acquireEditLock(User::factory()->officer()->create());

        $response = $this->actingAs($this->officer)
            ->from(route('management.bosses.index'))
            ->delete(route('management.bosses.destroy', $boss));

        $response->assertSessionHas('error', "Someone is editing Moroes, so it can't be deleted right now.");
        $this->assertModelExists($boss);
    }

    #[Group('authorization')]
    #[Test]
    public function it_forbids_destroy_without_edit_datasets_permission(): void
    {
        $boss = Boss::factory()->create();

        $response = $this->actingAs($this->dashboardOnlyUser())->delete(route('management.bosses.destroy', $boss));

        $response->assertForbidden();
        $this->assertModelExists($boss);
    }

    // ==================== helpers ====================

    private function dashboardOnlyUser(): User
    {
        return User::factory()->withPermissions('view-officer-dashboard')->create();
    }

    private function editUrl(Boss $boss): string
    {
        return route('management.bosses.edit', $boss);
    }

    private function eventInHours(int $startsInHours, int $endsInHours): Event
    {
        return Event::factory()->live()->create([
            'start_time' => now()->addHours($startsInHours),
            'end_time' => now()->addHours($endsInHours),
        ]);
    }

    /**
     * Fake UpdateBoss and expect it to run once, for the given boss, with
     * exactly the given validated data.
     *
     * @param  array<string, mixed>  $data
     */
    private function expectUpdate(Boss $boss, array $data): void
    {
        UpdateBoss::shouldRun()
            ->once()
            ->with($this->isBoss($boss), $this->identicalTo($data));
    }

    /**
     * Match the route-bound copy of the given boss in a faked action's arguments.
     */
    private function isBoss(Boss $boss): MatcherInterface
    {
        return Mockery::on(fn (Boss $given): bool => $given->is($boss));
    }

    /**
     * @return list<Media>
     */
    private function addImages(Boss $boss, int $count): array
    {
        return collect(range(1, $count))
            ->map(fn (int $number): Media => $boss->addMedia(UploadedFile::fake()->image("image-{$number}.png"))->toMediaCollection())
            ->all();
    }
}
