<?php

namespace Tests\Feature\Http\Controllers;

use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use App\Jobs\AttachRenderToCharacter;
use App\Models\Character;
use App\Models\Event;
use App\Models\PlayableRace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('home')]
class HomeControllerTest extends TestCase
{
    use MocksBlizzardServices;
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
    public function it_passes_the_launch_date_and_invite_url(): void
    {
        config(['guild.discord_invite_url' => 'https://discord.gg/example-invite']);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('foreverLaunchAt', config('guild.forever_launch_at'))
                ->where('discordInviteUrl', 'https://discord.gg/example-invite')
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
                    ->hasAll(['id', 'title', 'start_time', 'end_time', 'raids'])
                    ->missing('channel')
                    ->missing('channel_id')
                )->etc())
            );
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

    #[Test]
    public function it_renders_with_the_forever_theme(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('theme', 'forever')->etc());
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
        Character::factory()->create(['name' => 'Caldru']);
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload->etc()));

        Queue::assertPushed(AttachRenderToCharacter::class, fn ($job) => str_contains((string) $job->assetUrl, 'main-raw'));
    }

    #[Test]
    public function it_selects_the_main_raw_asset_and_not_the_avatar(): void
    {
        Queue::fake();
        Character::factory()->create(['name' => 'Caldru']);
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload->etc()));

        Queue::assertPushed(
            AttachRenderToCharacter::class,
            fn ($job) => ! str_contains((string) $job->assetUrl, 'avatar'),
        );
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
    public function a_blizzard_outage_does_not_break_the_homepage(): void
    {
        Character::factory()->create(['name' => 'Caldru']);
        Saloon::fake([
            GetCharacterMediaRequest::class => MockResponse::make(body: '', status: 503),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload->where('officerRenders.Caldru', null)->etc())
            );
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
