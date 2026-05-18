<?php

namespace Tests\Feature\Raiding;

use App\Models\DiscordRole;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use App\Models\Permission;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplyTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected DiscordRole $officerRole;

    protected User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->officerRole = DiscordRole::create([
            'id' => '829022020301094901',
            'name' => 'Officer',
            'position' => 5,
            'is_visible' => true,
        ]);

        $this->officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-raid-plans', 'guard_name' => 'web']));

        $this->officer = User::factory()->create();
        $this->officer->discordRoles()->attach($this->officerRole->id);

        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('getChannel')->andReturn(
                new Channel(id: '123456789', name: 'raids', position: 1),
            )->byDefault();
        });
    }

    #[Test]
    public function it_appends_template_groups_and_assignments_to_a_live_event(): void
    {
        $template = Event::factory()->template()->create();
        $group = EventAssignmentGroup::factory()->for($template)->create(['sort_order' => 0]);
        EventAssignment::factory()->for($template)->create(['group_id' => $group->id, 'sort_order' => 0]);

        $event = Event::factory()->live()->create();

        $this->actingAs($this->officer)
            ->post(route('raiding.plans.apply-template', $event), ['template_id' => $template->id]);

        $this->assertDatabaseHas('event_assignment_groups', ['event_id' => $event->id]);
        $this->assertDatabaseHas('event_assignments', ['event_id' => $event->id]);
    }

    #[Test]
    public function applying_a_template_does_not_remove_existing_groups(): void
    {
        $existingGroup = EventAssignmentGroup::factory()->create(['sort_order' => 0]);
        $event = $existingGroup->event;

        $template = Event::factory()->template()->create();
        EventAssignmentGroup::factory()->for($template)->create(['sort_order' => 0]);

        $this->actingAs($this->officer)
            ->post(route('raiding.plans.apply-template', $event), ['template_id' => $template->id]);

        $this->assertDatabaseHas('event_assignment_groups', ['id' => $existingGroup->id]);
        $this->assertSame(2, EventAssignmentGroup::where('event_id', $event->id)->count());
    }

    #[Test]
    public function new_groups_have_sort_order_offset_after_existing_groups(): void
    {
        $existingGroup = EventAssignmentGroup::factory()->create(['sort_order' => 3]);
        $event = $existingGroup->event;

        $template = Event::factory()->template()->create();
        EventAssignmentGroup::factory()->for($template)->create(['sort_order' => 0]);

        $this->actingAs($this->officer)
            ->post(route('raiding.plans.apply-template', $event), ['template_id' => $template->id]);

        $newGroup = EventAssignmentGroup::where('event_id', $event->id)
            ->where('id', '!=', $existingGroup->id)
            ->firstOrFail();

        $this->assertSame(4, $newGroup->sort_order);
    }

    #[Test]
    public function it_copies_ungrouped_assignments_from_template(): void
    {
        $template = Event::factory()->template()->create();
        EventAssignment::factory()->for($template)->create(['group_id' => null, 'boss_id' => null, 'sort_order' => 0]);

        $event = Event::factory()->live()->create();

        $this->actingAs($this->officer)
            ->post(route('raiding.plans.apply-template', $event), ['template_id' => $template->id]);

        $this->assertDatabaseHas('event_assignments', [
            'event_id' => $event->id,
            'group_id' => null,
            'boss_id' => null,
        ]);
    }

    #[Test]
    public function it_returns_403_for_users_without_update_permission(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->live()->create();
        $template = Event::factory()->template()->create();

        $response = $this->actingAs($user)
            ->post(route('raiding.plans.apply-template', $event), ['template_id' => $template->id]);

        $response->assertForbidden();
    }

    #[Test]
    public function it_returns_404_if_template_id_is_a_live_event(): void
    {
        $event = Event::factory()->live()->create();
        $notATemplate = Event::factory()->live()->create();

        $response = $this->actingAs($this->officer)
            ->post(route('raiding.plans.apply-template', $event), ['template_id' => $notATemplate->id]);

        $response->assertNotFound();
    }
}
