<?php

namespace Tests\Feature\Http\Controllers;

use App\Contracts\HasCharacterMedia;
use App\Jobs\AttachRenderToCharacter;
use App\Models\Character;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\PlayableRace;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('home')]
class HomeControllerTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    // ==================== index ====================

    #[Test]
    public function the_homepage_renders(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Home'));
    }

    #[Test]
    public function the_homepage_declares_a_dark_color_scheme_and_forever_theme_color(): void
    {
        $this->get(route('home'))
            ->assertSee('<meta name="theme-color" content="#003c4e">', false)
            ->assertSee('style="color-scheme: dark"', false);
    }

    #[Test]
    public function other_pages_use_the_classic_theme_color(): void
    {
        $this->get(route('privacypolicy'))
            ->assertSee('<meta name="theme-color" content="#4a3d32">', false);
    }

    #[Test]
    public function it_passes_the_launch_date_without_its_own_invite_url(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('foreverLaunchAt', config('guild.forever_launch_at'))
                ->missing('discordInviteUrl')
                ->etc()
            );
    }

    #[Test]
    public function it_passes_all_eight_officers(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->has('officers', 8)->etc());
    }

    #[Test]
    public function it_lists_upcoming_events_in_chronological_order(): void
    {
        $later = Event::factory()->live()->create([
            'title' => 'Later raid',
            'start_time' => now()->addDays(5),
            'end_time' => now()->addDays(5)->addHours(3),
        ]);
        $sooner = Event::factory()->live()->create([
            'title' => 'Sooner raid',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('upcomingEvents')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('upcomingEvents', 2)
                    ->where('upcomingEvents.0.title', 'Sooner raid')
                    ->where('upcomingEvents.1.title', 'Later raid')
                    ->etc()
                )
            );
    }

    #[Test]
    public function it_excludes_past_events(): void
    {
        Event::factory()->live()->create([
            'title' => 'Old raid',
            'start_time' => now()->subWeek(),
            'end_time' => now()->subWeek()->addHours(3),
        ]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('upcomingEvents')
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('upcomingEvents', 0)->etc())
            );
    }

    #[Test]
    public function it_excludes_template_events(): void
    {
        Event::factory()->create([
            'title' => 'Template raid',
            'is_template' => true,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('upcomingEvents')
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('upcomingEvents', 0)->etc())
            );
    }

    #[Test]
    public function it_limits_the_list_to_five_events(): void
    {
        Event::factory()->live()->count(8)->create([
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('upcomingEvents')
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('upcomingEvents', 5)->etc())
            );
    }

    #[Test]
    public function it_never_exposes_discord_channel_data_to_anonymous_visitors(): void
    {
        Event::factory()->live()->create([
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('upcomingEvents')
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('upcomingEvents.0', fn (Assert $event) => $event
                    ->hasAll(['id', 'title', 'start_time', 'end_time', 'game_version', 'raids'])
                    ->missing('channel')
                    ->missing('channel_id')
                )->etc())
            );
    }

    #[Test]
    public function guests_see_upcoming_events_in_the_visitors_shape(): void
    {
        Event::factory()->live()->create([
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('upcomingEvents.0', fn (Assert $event) => $event
                    ->has('raids')
                    ->missing('duration')
                    ->etc()
                )->etc())
            );
    }

    #[Test]
    public function guests_never_receive_upcoming_events_cached_in_the_previous_raid_names_shape(): void
    {
        $event = Event::factory()->live()->create([
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);
        Cache::tags('raiding', 'events')->put('events:upcoming:public', [['id' => $event->id, 'raids' => ['Black Temple']]], now()->addMinutes(10));

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('upcomingEvents.0', fn (Assert $upcoming) => $upcoming
                    ->has('title')
                    ->etc()
                )->etc())
            );
    }

    #[Test]
    public function signed_in_users_see_upcoming_events_in_the_members_shape(): void
    {
        $this->fakeDiscordChannel(name: 'raid-chat', position: 3);
        Event::factory()->live()->create([
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('upcomingEvents.0', fn (Assert $event) => $event
                    ->has('duration')
                    ->has('raids')
                    ->where('channel.name', 'raid-chat')
                    ->etc()
                )->etc())
            );
    }

    #[Test]
    #[Group('laravel-media-library')]
    public function it_tags_each_upcoming_event_with_its_game_version_icon_fields(): void
    {
        Storage::fake('public');
        $gameVersion = GameVersion::factory()->create(['title' => 'World of Warcraft: Forever', 'color' => '226e73']);
        $gameVersion->addMedia(UploadedFile::fake()->image('icon.png'))->toMediaCollection(GameVersion::MEDIA_ICON);
        Event::factory()->live()->for($gameVersion)->create([
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('upcomingEvents.0.game_version', fn (Assert $version) => $version
                        ->where('id', $gameVersion->id)
                        ->where('title', 'World of Warcraft: Forever')
                        ->where('color', '226e73')
                        ->where('icon_url', fn (?string $url): bool => $url !== null && str_contains($url, 'icon'))
                    )
                    ->etc()
                )
            );
    }

    #[Test]
    public function it_sends_a_null_game_version_for_an_event_without_one(): void
    {
        Event::factory()->live()->create([
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('upcomingEvents.0.game_version', null)
                    ->etc()
                )
            );
    }

    #[Test]
    #[Group('laravel-media-library')]
    public function it_lists_upcoming_events_without_lazy_loading_game_version_media(): void
    {
        Storage::fake('public');
        config(['media-library.force_lazy_loading' => false]);

        foreach (range(1, 2) as $index) {
            $gameVersion = GameVersion::factory()->create();
            $gameVersion->addMedia(UploadedFile::fake()->image("icon-{$index}.png"))->toMediaCollection(GameVersion::MEDIA_ICON);
            Event::factory()->live()->for($gameVersion)->create([
                'start_time' => now()->addDay(),
                'end_time' => now()->addDay()->addHours(3),
            ]);
        }

        Model::preventLazyLoading();

        try {
            $this->get(route('home'))
                ->assertInertia(fn (Assert $page) => $page
                    ->loadDeferredProps(fn (Assert $reload) => $reload
                        ->has('upcomingEvents', 2)
                        ->where('upcomingEvents.0.game_version.icon_url', fn (?string $url): bool => $url !== null)
                        ->where('upcomingEvents.1.game_version.icon_url', fn (?string $url): bool => $url !== null)
                        ->etc()
                    )
                );
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    #[Test]
    public function anonymous_visitors_may_not_view_plans(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('canViewPlans', false)->etc());
    }

    #[Test]
    public function authenticated_users_may_view_plans(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('canViewPlans', true)->etc());
    }

    // ==================== page shell ====================

    #[Test]
    public function it_paints_the_forever_ground_on_the_body_of_a_full_homepage_load(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<body[^>]*\bbg-forever-800\b/', $content);
    }

    #[Test]
    public function other_pages_do_not_paint_the_forever_ground_on_the_body(): void
    {
        $content = $this->get(route('privacypolicy'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bbg-forever-800\b/', $content);
    }

    // ==================== officer renders ====================

    #[Test]
    public function it_returns_a_render_entry_for_every_configured_officer(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('officerRenders')
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('officerRenders', 8)->etc())
            );
    }

    #[Test]
    public function it_exposes_the_render_url_for_an_officer_with_attached_media(): void
    {
        Storage::fake('public');

        $character = Character::factory()->create(['name' => 'Caldru']);
        $character->addMediaFromString('RENDER')
            ->usingFileName('main-raw.png')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('officerRenders.Caldru.url', fn (?string $url) => $url !== null && str_contains($url, 'main-raw'))
                    ->etc()
                )
            );
    }

    #[Test]
    public function it_exposes_the_visible_bounds_for_an_officer_with_measured_media(): void
    {
        Storage::fake('public');

        $character = Character::factory()->create(['name' => 'Caldru']);
        $character->addMediaFromString('RENDER')
            ->usingFileName('main-raw.png')
            ->withCustomProperties(['visible_top' => 0.27, 'visible_bottom' => 0.86])
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('officerRenders.Caldru.visibleTop', 0.27)
                    ->where('officerRenders.Caldru.visibleBottom', 0.86)
                    ->etc()
                )
            );
    }

    #[Test]
    public function it_defaults_visible_bounds_to_the_full_frame_when_unmeasured(): void
    {
        Storage::fake('public');

        $character = Character::factory()->create(['name' => 'Caldru']);
        $character->addMediaFromString('RENDER')
            ->usingFileName('main-raw.png')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('officerRenders.Caldru.visibleTop', 0)
                    ->where('officerRenders.Caldru.visibleBottom', 1)
                    ->etc()
                )
            );
    }

    #[Test]
    public function it_flags_small_race_officers_for_a_smaller_render(): void
    {
        Storage::fake('public');

        PlayableRace::factory()->create(['id' => 7]);
        $character = Character::factory()->create(['name' => 'Caldru', 'playable_race_id' => 7]);
        $character->addMediaFromString('RENDER')
            ->usingFileName('main-raw.png')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('officerRenders.Caldru.isSmallRace', true)
                    ->etc()
                )
            );
    }

    #[Test]
    public function it_does_not_flag_a_non_small_race_officer(): void
    {
        Storage::fake('public');

        PlayableRace::factory()->create(['id' => 1]);
        $character = Character::factory()->create(['name' => 'Caldru', 'playable_race_id' => 1]);
        $character->addMediaFromString('RENDER')
            ->usingFileName('main-raw.png')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('officerRenders.Caldru.isSmallRace', false)
                    ->etc()
                )
            );
    }

    #[Test]
    public function it_returns_null_for_an_officer_with_no_character_row(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->where('officerRenders.Zargoat', null)->etc())
            );
    }

    #[Test]
    public function it_dispatches_a_render_job_for_an_officer_whose_media_is_missing(): void
    {
        Queue::fake();
        $character = Character::factory()->create(['name' => 'Caldru']);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload->etc()));

        Queue::assertPushed(AttachRenderToCharacter::class, fn ($job) => $job->characterId === $character->id);
    }

    #[Test]
    public function it_does_not_dispatch_a_render_job_when_media_already_exists(): void
    {
        Queue::fake();
        Storage::fake('public');

        $character = Character::factory()->create(['name' => 'Caldru']);
        $character->addMediaFromString('RENDER')
            ->usingFileName('main-raw.png')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload->etc()));

        Queue::assertNotPushed(AttachRenderToCharacter::class);
    }

    #[Test]
    public function the_homepage_renders_and_dispatches_the_render_job_regardless_of_blizzard_state(): void
    {
        Queue::fake();
        Character::factory()->create(['name' => 'Caldru']);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload->etc()));

        Queue::assertPushed(AttachRenderToCharacter::class);
    }

    #[Test]
    public function officer_lookup_is_case_insensitive_on_name(): void
    {
        Storage::fake('public');

        $character = Character::factory()->create(['name' => 'caldru']);
        $character->addMediaFromString('RENDER')
            ->usingFileName('main-raw.png')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('officerRenders.Caldru', fn ($render) => $render !== null)
                    ->etc()
                )
            );
    }
}
