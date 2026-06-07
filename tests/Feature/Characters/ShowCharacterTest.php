<?php

namespace Tests\Feature\Characters;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ShowCharacterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function member(): User
    {
        return User::factory()->withPermissions('view-officer-dashboard')->create();
    }

    private function characterSlug(Character $character): string
    {
        return Str::slug($character->name);
    }

    #[Test]
    public function show_is_accessible_without_authentication(): void
    {
        $character = Character::factory()->withPlayableClass()->withRank()->create();

        $response = $this->get(route('characters.show', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]));

        $response->assertOk();
    }

    #[Test]
    public function show_renders_character_overview(): void
    {
        $character = Character::factory()->withPlayableClass()->withRank()->create();
        $user = $this->member();

        $response = $this->actingAs($user)->get(route('characters.show', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roster/Characters/Show')
            ->has('character')
            ->missing('recent_reports')
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('recent_reports'))
        );
    }
}
