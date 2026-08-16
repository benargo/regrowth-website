<?php

namespace Tests\Unit\Http\Integrations\RaidHelper\Data\Compositions;

use App\Enums\SignupStatus;
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
        $this->assertSame(SignupStatus::Confirmed, $dto->isConfirmed);
        $this->assertSame('#c69b3a', $dto->color);
    }

    #[Test]
    public function it_casts_unconfirmed_slot(): void
    {
        $data = $this->sampleApiResponse();
        $data['isConfirmed'] = 'unconfirmed';

        $dto = CompositionSlotData::from($data);

        $this->assertSame(SignupStatus::Unconfirmed, $dto->isConfirmed);
    }

    #[Test]
    public function it_casts_cancelled_slot(): void
    {
        $data = $this->sampleApiResponse();
        $data['isConfirmed'] = 'cancelled';

        $dto = CompositionSlotData::from($data);

        $this->assertSame(SignupStatus::Cancelled, $dto->isConfirmed);
    }

    #[Test]
    public function it_defaults_signup_status_to_unconfirmed_when_is_confirmed_is_absent(): void
    {
        $data = $this->sampleApiResponse();
        unset($data['isConfirmed']);

        $dto = CompositionSlotData::from($data);

        $this->assertSame(SignupStatus::Unconfirmed, $dto->isConfirmed);
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
