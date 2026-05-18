<?php

namespace Tests\Feature\Dashboard;

use App\Models\DiscordRole;
use App\Models\Event;
use App\Models\Permission;
use App\Models\Raid;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected DiscordRole $officerRole;

    protected User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->officerRole = DiscordRole::create([
            'id' => '829022020301094900',
            'name' => 'Officer',
            'position' => 5,
            'is_visible' => true,
        ]);

        $this->officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-raid-plans', 'guard_name' => 'web']));
        $this->officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-officer-dashboard', 'guard_name' => 'web']));

        $this->officer = User::factory()->create();
        $this->officer->discordRoles()->attach($this->officerRole->id);

        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('getChannel')->andReturn(
                new Channel(id: '123456789', name: 'raids', position: 1),
            )->byDefault();
        });
    }

    // ─── index ───────────────────────────────────────────────────────────────────

    #[Test]
    public function it_renders_the_index_page_for_officers(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.event-templates.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('EventTemplates/Index'));
    }

    #[Test]
    public function it_returns_templates_and_raid_groups_on_index(): void
    {
        $raid = Raid::factory()->create();
        $template = Event::factory()->template()->create(['title' => 'SSC Setup']);
        $template->raids()->attach($raid->id);

        $response = $this->actingAs($this->officer)->get(route('dashboard.event-templates.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('templates', 1)
            ->where('templates.0.title', 'SSC Setup')
            ->has('raidGroups', 1)
            ->where('raidGroups.0.raid.id', $raid->id)
            ->has('raidGroups.0.templates', 1)
        );
    }

    #[Test]
    public function a_multi_raid_template_appears_under_each_raid_in_raid_groups(): void
    {
        $raidA = Raid::factory()->create(['name' => 'SSC']);
        $raidB = Raid::factory()->create(['name' => 'TK']);
        $template = Event::factory()->template()->create(['title' => 'Combined']);
        $template->raids()->attach([$raidA->id, $raidB->id]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.event-templates.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('raidGroups', 2)
        );
    }

    #[Test]
    public function it_returns_403_for_unauthenticated_users_on_index(): void
    {
        $response = $this->get(route('dashboard.event-templates.index'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function it_returns_403_for_users_without_manage_raid_plans_on_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard.event-templates.index'));

        $response->assertForbidden();
    }

    // ─── create ──────────────────────────────────────────────────────────────────

    #[Test]
    public function it_renders_the_create_page_with_raids(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->actingAs($this->officer)->get(route('dashboard.event-templates.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('EventTemplates/Create')
            ->has('raids', 1)
            ->where('raids.0.id', $raid->id)
        );
    }

    // ─── store ───────────────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_template_and_redirects_to_edit(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('dashboard.event-templates.store'), [
            'title' => 'My Template',
            'raid_ids' => [$raid->id],
        ]);

        $template = Event::templates()->where('title', 'My Template')->firstOrFail();

        $this->assertTrue($template->is_template);
        $this->assertTrue($template->raids()->where('raids.id', $raid->id)->exists());

        $response->assertRedirect(route('dashboard.event-templates.edit', $template));
    }

    #[Test]
    public function store_requires_a_title(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('dashboard.event-templates.store'), [
            'title' => '',
            'raid_ids' => [$raid->id],
        ]);

        $response->assertSessionHasErrors('title');
    }

    #[Test]
    public function store_requires_at_least_one_raid(): void
    {
        $response = $this->actingAs($this->officer)->post(route('dashboard.event-templates.store'), [
            'title' => 'My Template',
            'raid_ids' => [],
        ]);

        $response->assertSessionHasErrors('raid_ids');
    }

    // ─── edit ────────────────────────────────────────────────────────────────────

    #[Test]
    public function it_renders_the_edit_page_for_a_template(): void
    {
        $template = Event::factory()->template()->create();

        $response = $this->actingAs($this->officer)->get(route('dashboard.event-templates.edit', $template));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('EventTemplates/Edit')
            ->has('template')
            ->has('raids')
        );
    }

    #[Test]
    public function it_returns_403_for_unauthorized_user_on_edit(): void
    {
        $user = User::factory()->create();
        $template = Event::factory()->template()->create();

        $response = $this->actingAs($user)->get(route('dashboard.event-templates.edit', $template));

        $response->assertForbidden();
    }

    // ─── update ──────────────────────────────────────────────────────────────────

    #[Test]
    public function it_updates_the_template_title_and_raids(): void
    {
        $raidA = Raid::factory()->create();
        $raidB = Raid::factory()->create();
        $template = Event::factory()->template()->create(['title' => 'Old Title']);
        $template->raids()->attach($raidA->id);

        $response = $this->actingAs($this->officer)->patch(route('dashboard.event-templates.update', $template), [
            'title' => 'New Title',
            'raid_ids' => [$raidB->id],
        ]);

        $response->assertRedirect();

        $template->refresh();
        $this->assertSame('New Title', $template->title);
        $this->assertFalse($template->raids()->where('raids.id', $raidA->id)->exists());
        $this->assertTrue($template->raids()->where('raids.id', $raidB->id)->exists());
    }

    // ─── destroy ─────────────────────────────────────────────────────────────────

    #[Test]
    public function it_deletes_a_template_and_redirects_to_index(): void
    {
        $template = Event::factory()->template()->create();

        $response = $this->actingAs($this->officer)->delete(route('dashboard.event-templates.destroy', $template));

        $response->assertRedirect(route('dashboard.event-templates.index'));
        $this->assertDatabaseMissing('events', ['id' => $template->id]);
    }
}
