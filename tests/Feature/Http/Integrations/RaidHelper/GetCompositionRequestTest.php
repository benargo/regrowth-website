<?php

namespace Tests\Feature\Http\Integrations\RaidHelper;

use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionData;
use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use App\Http\Integrations\RaidHelper\Requests\GetCompositionRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Exceptions\Request\Statuses\NotFoundException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('raidhelper-integration')]
class GetCompositionRequestTest extends TestCase
{
    #[Test]
    public function it_maps_a_composition_payload_to_composition_data(): void
    {
        Saloon::fake([
            GetCompositionRequest::class => MockResponse::make($this->compositionPayload(), 200),
        ]);

        $dto = $this->connector()->send(new GetCompositionRequest('999000000000000001'))->dto();

        $this->assertInstanceOf(CompositionData::class, $dto);
        $this->assertCount(1, $dto->slots);
        $this->assertSame('Arthas', $dto->slots[0]->name);
        $this->assertTrue($dto->slots[0]->isConfirmed);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_throws_not_found_when_the_composition_is_missing(): void
    {
        Saloon::fake([
            GetCompositionRequest::class => MockResponse::make(['reason' => 'unknown composition', 'status' => 'failed'], 404),
        ]);

        $this->expectException(NotFoundException::class);

        $this->connector()->send(new GetCompositionRequest('404040404'));
    }

    private function connector(): RaidHelperConnector
    {
        return new RaidHelperConnector(token: 'test-token', serverId: '111222333444555666');
    }

    private function compositionPayload(): array
    {
        return [
            'id' => '999000000000000001',
            'title' => 'Weekly Composition',
            'editPermissions' => 'managers',
            'showRoles' => true,
            'showClasses' => true,
            'groupCount' => 1,
            'slotCount' => 1,
            'groups' => [],
            'dividers' => [],
            'classes' => [],
            'slots' => [[
                'id' => 'slot-1', 'name' => 'Arthas', 'groupNumber' => 1, 'slotNumber' => 1,
                'className' => 'Warrior', 'classEmoteId' => '0', 'specName' => 'Arms',
                'specEmoteId' => '0', 'isConfirmed' => 'confirmed', 'color' => '0,0,0',
            ]],
        ];
    }
}
