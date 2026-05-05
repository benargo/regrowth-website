<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\CharacterResource;
use App\Http\Resources\EventGroupsResource;
use App\Models\Character;
use App\Models\Raid;
use App\Services\RaidHelper\Resources\Comp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventGroupsResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeComp(array $groups = [], array $slots = []): Comp
    {
        return Comp::from([
            'id' => 'comp-1',
            'title' => 'Test Comp',
            'editPermissions' => 'managers',
            'showRoles' => true,
            'showClasses' => true,
            'groupCount' => count($groups),
            'slotCount' => count($slots),
            'groups' => $groups,
            'dividers' => [],
            'classes' => [],
            'slots' => $slots,
        ]);
    }

    private function makeGroup(int $position, string $name = 'Group'): array
    {
        return ['name' => $name.' '.$position, 'position' => $position];
    }

    private function makeSlot(string $id, string $name, int $groupNumber, int $slotNumber): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'groupNumber' => $groupNumber,
            'slotNumber' => $slotNumber,
            'className' => 'Warrior',
            'classEmoteId' => '111',
            'specName' => 'Arms',
            'specEmoteId' => '222',
            'isConfirmed' => 'confirmed',
            'color' => '#C69B3D',
        ];
    }

    #[Test]
    public function it_throws_an_exception_when_not_given_a_comp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('EventGroupsResource can only be created from a Comp data object.');

        new EventGroupsResource('not-a-comp');
    }

    #[Test]
    public function it_returns_an_empty_array_when_comp_has_no_groups(): void
    {
        $comp = $this->makeComp();

        $array = (new EventGroupsResource($comp))->toArray(new Request);

        $this->assertEmpty($array);
    }

    #[Test]
    public function it_returns_one_key_per_group_zero_indexed(): void
    {
        $comp = $this->makeComp(
            groups: [$this->makeGroup(1), $this->makeGroup(2)],
            slots: [
                $this->makeSlot('s1', 'Alice', 1, 1),
                $this->makeSlot('s2', 'Bob', 2, 1),
            ],
        );

        $array = (new EventGroupsResource($comp))->toArray(new Request);

        $this->assertArrayHasKey(0, $array);
        $this->assertArrayHasKey(1, $array);
        $this->assertCount(2, $array);
    }

    #[Test]
    public function it_maps_slot_positions_correctly_within_a_group(): void
    {
        $comp = $this->makeComp(
            groups: [$this->makeGroup(1)],
            slots: [
                $this->makeSlot('s1', 'Alice', 1, 1),
                $this->makeSlot('s2', 'Bob', 1, 2),
            ],
        );

        $array = (new EventGroupsResource($comp))->toArray(new Request);

        $groupSlots = $array[0];
        $this->assertArrayHasKey(0, $groupSlots); // group 1, slot 1 → position 0
        $this->assertArrayHasKey(1, $groupSlots); // group 1, slot 2 → position 1
    }

    #[Test]
    public function it_calculates_slot_position_across_multiple_groups(): void
    {
        $comp = $this->makeComp(
            groups: [$this->makeGroup(1), $this->makeGroup(2)],
            slots: [
                $this->makeSlot('s1', 'Alice', 1, 1),
                $this->makeSlot('s2', 'Bob', 2, 3),
            ],
        );

        $array = (new EventGroupsResource($comp))->toArray(new Request);

        $this->assertArrayHasKey(0, $array[0]); // group 1, slot 1 → (1-1)*5 + 1-1 = 0
        $this->assertArrayHasKey(7, $array[1]); // group 2, slot 3 → (2-1)*5 + 3-1 = 7
    }

    #[Test]
    public function it_returns_null_for_slots_with_no_matching_character(): void
    {
        $comp = $this->makeComp(
            groups: [$this->makeGroup(1)],
            slots: [$this->makeSlot('s1', 'UnknownPlayer', 1, 1)],
        );

        $array = (new EventGroupsResource($comp))->toArray(new Request);

        $this->assertNull($array[0][0]);
    }

    #[Test]
    public function it_returns_a_character_resource_for_slots_with_a_matching_character(): void
    {
        Character::factory()->create(['name' => 'Shiniko']);

        $comp = $this->makeComp(
            groups: [$this->makeGroup(1)],
            slots: [$this->makeSlot('s1', 'Shiniko', 1, 1)],
        );

        $array = (new EventGroupsResource($comp))->toArray(new Request);

        $this->assertInstanceOf(CharacterResource::class, $array[0][0]);
        $this->assertSame('Shiniko', $array[0][0]->resource->name);
    }

    #[Test]
    public function it_restricts_groups_and_slots_when_for_raid_is_called(): void
    {
        $raid = Raid::factory()->create(['max_players' => 10]); // maxGroups = 2

        $comp = $this->makeComp(
            groups: [$this->makeGroup(1), $this->makeGroup(2), $this->makeGroup(3)],
            slots: [
                $this->makeSlot('s1', 'Alice', 1, 1),
                $this->makeSlot('s2', 'Bob', 2, 1),
                $this->makeSlot('s3', 'Charlie', 3, 1),
            ],
        );

        $array = (new EventGroupsResource($comp))->forRaid($raid)->toArray(new Request);

        $this->assertCount(2, $array);
        $this->assertArrayHasKey(0, $array);
        $this->assertArrayHasKey(1, $array);
        $this->assertArrayNotHasKey(2, $array);
    }

    #[Test]
    public function it_excludes_slots_outside_raid_max_groups_when_for_raid_is_called(): void
    {
        $raid = Raid::factory()->create(['max_players' => 5]); // maxGroups = 1

        $comp = $this->makeComp(
            groups: [$this->makeGroup(1), $this->makeGroup(2)],
            slots: [
                $this->makeSlot('s1', 'Alice', 1, 1),
                $this->makeSlot('s2', 'Bob', 2, 1),
            ],
        );

        $array = (new EventGroupsResource($comp))->forRaid($raid)->toArray(new Request);

        $this->assertCount(1, $array);
        $this->assertArrayHasKey(0, $array);
        $this->assertCount(1, $array[0]);
    }

    #[Test]
    public function it_returns_self_from_for_raid_for_chaining(): void
    {
        $raid = Raid::factory()->create(['max_players' => 10]);
        $comp = $this->makeComp();

        $resource = new EventGroupsResource($comp);

        $this->assertSame($resource, $resource->forRaid($raid));
    }
}
