<?php

namespace Tests\Feature\Dashboard;

use App\Enums\RecruitmentMacroType;
use App\Models\RecruitmentMacro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('recruitment')]
class RecruitmentMacroControllerTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        return User::factory()->withPermissions('view-recruitment-macros')->create();
    }

    private function manager(): User
    {
        return User::factory()->withPermissions('view-recruitment-macros', 'manage-recruitment-macros')->create();
    }

    private function deleteAnyOfficer(): User
    {
        return User::factory()->withPermissions('view-recruitment-macros', 'manage-recruitment-macros', 'delete-any-recruitment-macro')->create();
    }

    /**
     * @return array{title: string, type: string, content: string}
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Trade chat',
            'type' => RecruitmentMacroType::InGame->value,
            'content' => 'Regrowth is recruiting!',
            ...$overrides,
        ];
    }

    // ==================== access ====================

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get(route('management.recruitment-macros.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function users_without_the_view_permission_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('management.recruitment-macros.index'))
            ->assertForbidden();
    }

    #[Test]
    public function viewers_cannot_change_macros(): void
    {
        $viewer = $this->viewer();
        $macro = RecruitmentMacro::factory()->for($viewer, 'createdBy')->create();

        $this->actingAs($viewer);
        $this->get(route('management.recruitment-macros.create'))->assertForbidden();
        $this->post(route('management.recruitment-macros.store'), $this->payload())->assertForbidden();
        $this->get(route('management.recruitment-macros.edit', $macro))->assertForbidden();
        $this->patch(route('management.recruitment-macros.update', $macro), $this->payload())->assertForbidden();
        $this->put(route('management.recruitment-macros.reorder'), ['macro_ids' => [$macro->id]])->assertForbidden();
        $this->delete(route('management.recruitment-macros.destroy', $macro))->assertForbidden();
    }

    // ==================== index ====================

    #[Test]
    #[Group('happy-path')]
    public function index_lists_live_macros_in_order(): void
    {
        $viewer = $this->viewer();
        $first = RecruitmentMacro::factory()->create();
        $second = RecruitmentMacro::factory()->discord()->create();

        $this->actingAs($viewer)
            ->get(route('management.recruitment-macros.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manage/RecruitmentMacros/Index')
                ->has('macros.data', 2)
                ->where('macros.data.0.id', $first->id)
                ->where('macros.data.1.id', $second->id)
                ->where('macros.data.1.type', 'discord')
                ->where('macros.data.1.character_limit', 2000)
                ->where('macros.data.0.created_by.id', $first->created_by)
                ->has('trashed.data', 0)
            );
    }

    #[Test]
    public function index_only_lists_the_trashed_macros_the_user_can_restore(): void
    {
        $manager = $this->manager();
        $own = RecruitmentMacro::factory()->for($manager, 'createdBy')->create();
        $others = RecruitmentMacro::factory()->create();
        $own->delete();
        $others->delete();

        $this->actingAs($manager)
            ->get(route('management.recruitment-macros.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('trashed.data', 1)
                ->where('trashed.data.0.id', $own->id)
                ->where('trashed.data.0.meta.can_restore', true)
                ->where('trashed.data.0.meta.days_remaining', 30)
            );

        $this->actingAs($this->deleteAnyOfficer())
            ->get(route('management.recruitment-macros.index'))
            ->assertInertia(fn (Assert $page) => $page->has('trashed.data', 2));
    }

    // ==================== create and store ====================

    #[Test]
    #[Group('happy-path')]
    public function create_renders_the_form_with_the_macro_types(): void
    {
        $this->actingAs($this->manager())
            ->get(route('management.recruitment-macros.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manage/RecruitmentMacros/Create')
                ->where('types.0', ['value' => 'in_game', 'label' => 'In-game chat', 'character_limit' => 255])
                ->where('types.1', ['value' => 'discord', 'label' => 'Discord', 'character_limit' => 2000])
                ->has('mentions')
            );
    }

    #[Test]
    #[Group('happy-path')]
    public function store_creates_the_macro_for_its_creator_and_redirects_to_edit(): void
    {
        $manager = $this->manager();

        $response = $this->actingAs($manager)->post(route('management.recruitment-macros.store'), $this->payload());

        $macro = RecruitmentMacro::sole();
        $response->assertRedirect(route('management.recruitment-macros.edit', $macro))
            ->assertSessionHas('success', 'Added Trade chat.');
        $this->assertSame($manager->id, $macro->created_by);
    }

    #[Test]
    #[Group('validation')]
    public function store_rejects_content_over_the_type_limit(): void
    {
        $this->actingAs($this->manager())
            ->post(route('management.recruitment-macros.store'), $this->payload(['content' => str_repeat('x', 256)]))
            ->assertSessionHasErrors(['content' => 'The macro is 256 characters long; In-game chat allows 255.']);

        $this->assertDatabaseCount('recruitment_macros', 0);
    }

    // ==================== edit and update ====================

    #[Test]
    public function edit_renders_the_macro_with_whether_the_user_can_delete_it(): void
    {
        $manager = $this->manager();
        $macro = RecruitmentMacro::factory()->create();

        $this->actingAs($manager)
            ->get(route('management.recruitment-macros.edit', $macro))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manage/RecruitmentMacros/Edit')
                ->where('macro.data.id', $macro->id)
                ->where('macro.data.meta.can_delete', false)
                ->has('types', 2)
            );
    }

    #[Test]
    #[Group('happy-path')]
    public function any_manager_can_update_someone_elses_macro(): void
    {
        $macro = RecruitmentMacro::factory()->create();

        $this->actingAs($this->manager())
            ->patch(route('management.recruitment-macros.update', $macro), $this->payload(['title' => 'LFG channel', 'content' => "Line one\r\nLine two"]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Saved LFG channel.');

        $macro->refresh();
        $this->assertSame('LFG channel', $macro->title);
        $this->assertSame("Line one\nLine two", $macro->content);
    }

    #[Test]
    public function edit_returns_404_for_a_trashed_macro(): void
    {
        $macro = RecruitmentMacro::factory()->create();
        $macro->delete();

        $this->actingAs($this->manager())
            ->get(route('management.recruitment-macros.edit', $macro))
            ->assertNotFound();
    }

    // ==================== reorder ====================

    #[Test]
    public function reorder_stores_the_new_order(): void
    {
        [$first, $second] = RecruitmentMacro::factory()->count(2)->create();

        $this->actingAs($this->manager())
            ->put(route('management.recruitment-macros.reorder'), ['macro_ids' => [$second->id, $first->id]])
            ->assertRedirect();

        $this->assertSame([$second->id, $first->id], RecruitmentMacro::ordered()->pluck('id')->all());
    }

    #[Test]
    #[Group('validation')]
    public function reorder_rejects_an_out_of_date_list(): void
    {
        [$first] = RecruitmentMacro::factory()->count(2)->create();

        $this->actingAs($this->manager())
            ->put(route('management.recruitment-macros.reorder'), ['macro_ids' => [$first->id]])
            ->assertSessionHasErrors(['macro_ids' => 'The macro list has changed; reload the page and try again.']);
    }

    // ==================== destroy ====================

    #[Test]
    #[Group('happy-path')]
    public function the_creator_can_delete_their_macro(): void
    {
        $manager = $this->manager();
        $macro = RecruitmentMacro::factory()->for($manager, 'createdBy')->create(['title' => 'Trade chat']);

        $this->actingAs($manager)
            ->delete(route('management.recruitment-macros.destroy', $macro))
            ->assertRedirect(route('management.recruitment-macros.index'))
            ->assertSessionHas('success', 'Deleted Trade chat. You can restore it for 30 days.');

        $this->assertSoftDeleted($macro);
    }

    #[Test]
    public function another_manager_cannot_delete_someone_elses_macro(): void
    {
        $macro = RecruitmentMacro::factory()->create();

        $this->actingAs($this->manager())
            ->delete(route('management.recruitment-macros.destroy', $macro))
            ->assertForbidden();

        $this->assertNotSoftDeleted($macro);
    }

    #[Test]
    public function delete_any_officers_can_delete_someone_elses_macro(): void
    {
        $macro = RecruitmentMacro::factory()->create();

        $this->actingAs($this->deleteAnyOfficer())
            ->delete(route('management.recruitment-macros.destroy', $macro))
            ->assertRedirect(route('management.recruitment-macros.index'));

        $this->assertSoftDeleted($macro);
    }

    // ==================== restore ====================

    #[Test]
    #[Group('happy-path')]
    public function the_creator_can_restore_their_macro(): void
    {
        $manager = $this->manager();
        $macro = RecruitmentMacro::factory()->for($manager, 'createdBy')->create(['title' => 'Trade chat']);
        $macro->delete();

        $this->actingAs($manager)
            ->post(route('management.recruitment-macros.restore', $macro))
            ->assertRedirect(route('management.recruitment-macros.index'))
            ->assertSessionHas('success', 'Restored Trade chat.');

        $this->assertNotSoftDeleted($macro);
    }

    #[Test]
    public function another_manager_cannot_restore_someone_elses_macro(): void
    {
        $macro = RecruitmentMacro::factory()->create();
        $macro->delete();

        $this->actingAs($this->manager())
            ->post(route('management.recruitment-macros.restore', $macro))
            ->assertForbidden();

        $this->assertSoftDeleted($macro);
    }

    #[Test]
    public function restoring_a_live_macro_returns_404(): void
    {
        $manager = $this->manager();
        $macro = RecruitmentMacro::factory()->for($manager, 'createdBy')->create();

        $this->actingAs($manager)
            ->post(route('management.recruitment-macros.restore', $macro))
            ->assertNotFound();
    }
}
