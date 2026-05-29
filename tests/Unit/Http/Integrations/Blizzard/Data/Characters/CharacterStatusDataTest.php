<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Characters;

use App\Http\Integrations\Blizzard\Data\Characters\CharacterStatusData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterStatusDataTest extends TestCase
{
    #[Test]
    public function it_casts_a_valid_character_status(): void
    {
        $dto = CharacterStatusData::from([
            'id' => 12345,
            'is_valid' => true,
        ]);

        $this->assertSame(12345, $dto->id);
        $this->assertTrue($dto->isValid);
    }

    #[Test]
    public function it_casts_an_invalid_character_status(): void
    {
        $dto = CharacterStatusData::from([
            'id' => 99,
            'is_valid' => false,
        ]);

        $this->assertSame(99, $dto->id);
        $this->assertFalse($dto->isValid);
    }
}
