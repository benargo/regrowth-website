<?php

namespace Tests\Feature;

use App\Services\Blizzard\GuildService;
use App\Services\Blizzard\PlayableClassService;
use App\Services\Blizzard\PlayableRaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuildRosterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(
            PlayableClassService::class,
            Mockery::mock(PlayableClassService::class, function (MockInterface $mock) {
                $mock->shouldReceive('index')->andReturn([
                    'classes' => [
                        ['id' => 7, 'name' => 'Shaman'],
                        ['id' => 1, 'name' => 'Warrior'],
                    ],
                ]);

                $mock->shouldReceive('find')
                    ->andReturnUsing(fn (int $id) => ['id' => $id, 'name' => "Class {$id}"]);

                $mock->shouldReceive('media')
                    ->andReturn(['assets' => [['key' => 'icon', 'value' => 'https://example.com/icon.jpg']]]);
            })
        );

        $this->instance(
            PlayableRaceService::class,
            Mockery::mock(PlayableRaceService::class, function (MockInterface $mock) {
                $mock->shouldReceive('index')->andReturn([
                    'races' => [
                        ['id' => 1, 'name' => 'Human'],
                        ['id' => 3, 'name' => 'Dwarf'],
                        ['id' => 4, 'name' => 'Night Elf'],
                        ['id' => 7, 'name' => 'Gnome'],
                        ['id' => 11, 'name' => 'Draenei'],
                    ],
                ]);

                $mock->shouldReceive('find')
                    ->andReturnUsing(fn (int $id) => ['id' => $id, 'name' => "Race {$id}"]);
            })
        );
    }

    #[Test]
    public function it_renders_roster_page(): void
    {
        $response = $this->get('/roster');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roster')
            ->has('classes')
            ->has('races')
            ->has('ranks')
            ->has('level_cap')
        );
    }

    #[Test]
    public function it_loads_members_via_deferred_prop(): void
    {
        $this->instance(
            GuildService::class,
            Mockery::mock(GuildService::class, function (MockInterface $mock) {
                $mock->shouldReceive('roster')->andReturn([
                    'members' => [
                        [
                            'character' => [
                                'id' => 12345,
                                'name' => 'Thrall',
                                'level' => 70,
                                'realm' => ['id' => 1, 'slug' => 'thunderstrike'],
                                'playable_class' => ['id' => 7],
                                'playable_race' => ['id' => 2],
                                'faction' => ['type' => 'ALLIANCE'],
                            ],
                            'rank' => 3,
                        ],
                    ],
                ]);
            })
        );

        $response = $this->get('/roster');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roster')
            ->missing('members') // Deferred prop
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('members', 1)
                ->where('members.0.character.name', 'Thrall')
                ->where('members.0.character.level', 70)
            )
        );
    }

    #[Test]
    public function it_enriches_members_with_playable_class_and_race(): void
    {
        $this->instance(
            GuildService::class,
            Mockery::mock(GuildService::class, function (MockInterface $mock) {
                $mock->shouldReceive('roster')->andReturn([
                    'members' => [
                        [
                            'character' => [
                                'id' => 1,
                                'name' => 'Jaina',
                                'level' => 70,
                                'realm' => ['id' => 1, 'slug' => 'thunderstrike'],
                                'playable_class' => ['id' => 8],
                                'playable_race' => ['id' => 1],
                                'faction' => ['type' => 'ALLIANCE'],
                            ],
                            'rank' => 0,
                        ],
                    ],
                ]);
            })
        );

        $response = $this->get('/roster');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('members')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('members', 1)
                ->has('members.0.character.playable_class')
                ->has('members.0.character.playable_race')
            )
        );
    }
}
