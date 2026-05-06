<?php

namespace Tests\Unit\Services\RaidHelper\Resources;

use App\Services\RaidHelper\Resources\Comp;
use App\Services\RaidHelper\Resources\CompDivider;
use App\Services\RaidHelper\Resources\CompGroup;
use App\Services\RaidHelper\Resources\CompSlot;
use App\Services\RaidHelper\Resources\EventClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompTest extends TestCase
{
    private function slotPayload(): array
    {
        return [
            'id' => '234728435400835073',
            'name' => 'Shiniko',
            'groupNumber' => 1,
            'slotNumber' => 1,
            'className' => 'Tank',
            'classEmoteId' => '580801859221192714',
            'specName' => 'Protection',
            'specEmoteId' => '637564444834136065',
            'isConfirmed' => 'confirmed',
            'color' => '#C69B6D',
        ];
    }

    private function groupPayload(): array
    {
        return ['name' => 'Group 1', 'position' => 1];
    }

    private function dividerPayload(): array
    {
        return ['name' => 'Edit Name', 'position' => 6];
    }

    private function specPayload(): array
    {
        return [
            'name' => 'Protection',
            'emoteId' => '637564444834136065',
            'roleEmoteId' => '598989638098747403',
            'color' => '#C69B6D',
        ];
    }

    private function classPayload(): array
    {
        return [
            'name' => 'Warrior',
            'emoteId' => '579532030153588739',
            'specs' => [$this->specPayload()],
        ];
    }

    private function payload(): array
    {
        return [
            'id' => '1498375689928446143',
            'title' => 'Composition Tool',
            'editPermissions' => 'managers',
            'showRoles' => true,
            'showClasses' => false,
            'groupCount' => 6,
            'slotCount' => 5,
            'groups' => [$this->groupPayload()],
            'dividers' => [$this->dividerPayload()],
            'classes' => [$this->classPayload()],
            'slots' => [$this->slotPayload()],
        ];
    }

    #[Test]
    public function it_constructs_from_a_full_payload(): void
    {
        $comp = Comp::from($this->payload());

        $this->assertSame('1498375689928446143', $comp->id);
        $this->assertSame('Composition Tool', $comp->title);
        $this->assertSame('managers', $comp->editPermissions);
        $this->assertTrue($comp->showRoles);
        $this->assertFalse($comp->showClasses);
        $this->assertSame(6, $comp->groupCount);
        $this->assertSame(5, $comp->slotCount);
    }

    #[Test]
    public function it_casts_group_count_string_to_integer(): void
    {
        $comp = Comp::from([...$this->payload(), 'groupCount' => '6']);

        $this->assertSame(6, $comp->groupCount);
    }

    #[Test]
    public function it_casts_slot_count_string_to_integer(): void
    {
        $comp = Comp::from([...$this->payload(), 'slotCount' => '5']);

        $this->assertSame(5, $comp->slotCount);
    }

    #[Test]
    public function it_hydrates_groups_as_comp_group_instances(): void
    {
        $comp = Comp::from($this->payload());

        $this->assertCount(1, $comp->groups);
        $this->assertInstanceOf(CompGroup::class, $comp->groups[0]);
        $this->assertSame('Group 1', $comp->groups[0]->name);
    }

    #[Test]
    public function it_hydrates_dividers_as_comp_divider_instances(): void
    {
        $comp = Comp::from($this->payload());

        $this->assertCount(1, $comp->dividers);
        $this->assertInstanceOf(CompDivider::class, $comp->dividers[0]);
        $this->assertSame('Edit Name', $comp->dividers[0]->name);
    }

    #[Test]
    public function it_hydrates_classes_as_event_class_instances(): void
    {
        $comp = Comp::from($this->payload());

        $this->assertCount(1, $comp->classes);
        $this->assertInstanceOf(EventClass::class, $comp->classes[0]);
        $this->assertSame('Warrior', $comp->classes[0]->name);
    }

    #[Test]
    public function it_hydrates_slots_as_comp_slot_instances(): void
    {
        $comp = Comp::from($this->payload());

        $this->assertCount(1, $comp->slots);
        $this->assertInstanceOf(CompSlot::class, $comp->slots[0]);
        $this->assertSame('Shiniko', $comp->slots[0]->name);
    }

    #[Test]
    public function it_accepts_empty_groups_array(): void
    {
        $comp = Comp::from([...$this->payload(), 'groups' => []]);

        $this->assertCount(0, $comp->groups);
    }

    #[Test]
    public function it_accepts_empty_dividers_array(): void
    {
        $comp = Comp::from([...$this->payload(), 'dividers' => []]);

        $this->assertCount(0, $comp->dividers);
    }

    #[Test]
    public function to_array_produces_snake_case_keys(): void
    {
        $comp = Comp::from($this->payload());
        $array = $comp->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('edit_permissions', $array);
        $this->assertArrayHasKey('show_roles', $array);
        $this->assertArrayHasKey('show_classes', $array);
        $this->assertArrayHasKey('group_count', $array);
        $this->assertArrayHasKey('slot_count', $array);
        $this->assertArrayHasKey('groups', $array);
        $this->assertArrayHasKey('dividers', $array);
        $this->assertArrayHasKey('classes', $array);
        $this->assertArrayHasKey('slots', $array);
    }
}
