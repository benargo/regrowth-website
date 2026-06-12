<?php

namespace Tests\Unit\Http\Integrations\RaidHelper\Data\Compositions;

use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionDividerData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompositionDividerDataTest extends TestCase
{
    #[Test]
    public function it_casts_from_array(): void
    {
        $dto = CompositionDividerData::from($this->sampleApiResponse());

        $this->assertSame('Tanks', $dto->name);
        $this->assertSame(2, $dto->position);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            'name' => 'Tanks',
            'position' => '2',
        ];
    }
}
