<?php

namespace Tests\Unit\Http\Integrations\RaidHelper\Data\Compositions;

use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionData;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionDividerData;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionGroupData;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionSlotData;
use App\Http\Integrations\RaidHelper\Data\Events\EventClassData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raidhelper-integration')]
class CompositionDataTest extends TestCase
{
    #[Test]
    public function it_casts_from_array(): void
    {
        $dto = CompositionData::from($this->sampleApiResponse());

        $this->assertSame('comp-xyz-999', $dto->id);
        $this->assertSame('Progression Roster', $dto->title);
        $this->assertSame('managers', $dto->editPermissions);
        $this->assertTrue($dto->showRoles);
        $this->assertFalse($dto->showClasses);
        $this->assertSame(2, $dto->groupCount);
        $this->assertSame(20, $dto->slotCount);

        $this->assertCount(1, $dto->groups);
        $this->assertInstanceOf(CompositionGroupData::class, $dto->groups[0]);
        $this->assertSame('Group 1', $dto->groups[0]->name);
        $this->assertSame(1, $dto->groups[0]->position);

        $this->assertCount(1, $dto->dividers);
        $this->assertInstanceOf(CompositionDividerData::class, $dto->dividers[0]);
        $this->assertSame('Tanks', $dto->dividers[0]->name);
        $this->assertSame(0, $dto->dividers[0]->position);

        $this->assertCount(1, $dto->classes);
        $this->assertInstanceOf(EventClassData::class, $dto->classes[0]);
        $this->assertSame('Warrior', $dto->classes[0]->name);
        $this->assertCount(1, $dto->classes[0]->specs);
        $this->assertSame('Protection', $dto->classes[0]->specs[0]->name);

        $this->assertCount(1, $dto->slots);
        $this->assertInstanceOf(CompositionSlotData::class, $dto->slots[0]);
        $this->assertSame('slot-abc-123', $dto->slots[0]->id);
        $this->assertTrue($dto->slots[0]->isConfirmed);
    }

    #[Test]
    public function it_handles_empty_collections(): void
    {
        $data = $this->sampleApiResponse();
        $data['groups'] = [];
        $data['dividers'] = [];
        $data['classes'] = [];
        $data['slots'] = [];

        $dto = CompositionData::from($data);

        $this->assertSame([], $dto->groups);
        $this->assertSame([], $dto->dividers);
        $this->assertSame([], $dto->classes);
        $this->assertSame([], $dto->slots);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            'id' => 'comp-xyz-999',
            'title' => 'Progression Roster',
            'editPermissions' => 'managers',
            'showRoles' => true,
            'showClasses' => false,
            'groupCount' => '2',
            'slotCount' => '20',
            'groups' => [
                [
                    'name' => 'Group 1',
                    'position' => '1',
                ],
            ],
            'dividers' => [
                [
                    'name' => 'Tanks',
                    'position' => '0',
                ],
            ],
            'classes' => [
                [
                    'name' => 'Warrior',
                    'emoteId' => '1111111111',
                    'specs' => [
                        [
                            'name' => 'Protection',
                            'emoteId' => '2222222222',
                            'roleEmoteId' => '3333333333',
                        ],
                    ],
                ],
            ],
            'slots' => [
                [
                    'id' => 'slot-abc-123',
                    'name' => 'Arthas',
                    'groupNumber' => '1',
                    'slotNumber' => '3',
                    'className' => 'Warrior',
                    'classEmoteId' => '1234567890',
                    'specName' => 'Protection',
                    'specEmoteId' => '9876543210',
                    'isConfirmed' => 'confirmed',
                    'color' => '#c69b3a',
                ],
            ],
        ];
    }
}
