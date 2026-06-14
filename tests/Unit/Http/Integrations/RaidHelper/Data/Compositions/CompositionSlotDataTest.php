<?php

namespace Tests\Unit\Http\Integrations\RaidHelper\Data\Compositions;

use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionSlotData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raidhelper-integration')]
class CompositionSlotDataTest extends TestCase
{
    #[Test]
    public function it_casts_from_array(): void
    {
        $dto = CompositionSlotData::from($this->sampleApiResponse());

        $this->assertSame('slot-abc-123', $dto->id);
        $this->assertSame('Arthas', $dto->name);
        $this->assertSame(1, $dto->groupNumber);
        $this->assertSame(3, $dto->slotNumber);
        $this->assertSame('Warrior', $dto->className);
        $this->assertSame('1234567890', $dto->classEmoteId);
        $this->assertSame('Protection', $dto->specName);
        $this->assertSame('9876543210', $dto->specEmoteId);
        $this->assertTrue($dto->isConfirmed);
        $this->assertSame('#c69b3a', $dto->color);
    }

    #[Test]
    public function it_casts_unconfirmed_slot(): void
    {
        $data = $this->sampleApiResponse();
        $data['isConfirmed'] = 'denied';

        $dto = CompositionSlotData::from($data);

        $this->assertFalse($dto->isConfirmed);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
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
        ];
    }
}
