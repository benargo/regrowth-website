<?php

namespace Tests\Unit\Http\Integrations\RaidHelper\Data\Compositions;

use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionGroupData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raidhelper-integration')]
class CompositionGroupDataTest extends TestCase
{
    #[Test]
    public function it_casts_from_array(): void
    {
        $dto = CompositionGroupData::from($this->sampleApiResponse());

        $this->assertSame('Group 1', $dto->name);
        $this->assertSame(1, $dto->position);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            'name' => 'Group 1',
            'position' => '1',
        ];
    }
}
