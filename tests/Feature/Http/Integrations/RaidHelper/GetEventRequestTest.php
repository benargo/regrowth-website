<?php

namespace Tests\Feature\Http\Integrations\RaidHelper;

use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use App\Http\Integrations\RaidHelper\Requests\GetEventRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('raidhelper-integration')]
class GetEventRequestTest extends TestCase
{
    #[Test]
    public function it_maps_an_event_payload_to_event_data(): void
    {
        Saloon::fake([
            GetEventRequest::class => MockResponse::make([
                'id' => '999000000000000001',
                'leaderId' => '200',
                'leaderName' => 'Leader',
                'channelId' => '100',
                'title' => 'Molten Core',
                'description' => '',
                'startTime' => 1700000000,
                'endTime' => 1700007200,
                'closingTime' => 1699999800,
                'closeTime' => null,
                'lastUpdated' => 1699999000,
                'color' => '0,0,0',
            ], 200),
        ]);

        $dto = $this->connector()->send(new GetEventRequest('999000000000000001'))->dto();

        $this->assertInstanceOf(EventData::class, $dto);
        $this->assertSame('999000000000000001', $dto->id);
        $this->assertSame('Molten Core', $dto->title);
    }

    #[Test]
    public function it_requests_the_event_endpoint(): void
    {
        Saloon::fake([
            GetEventRequest::class => MockResponse::make([
                'id' => '5', 'leaderId' => '1', 'leaderName' => 'L', 'channelId' => '1',
                'title' => 't', 'description' => '', 'startTime' => 1, 'endTime' => 2,
                'closingTime' => null, 'closeTime' => null, 'lastUpdated' => 1, 'color' => '0,0,0',
            ], 200),
        ]);

        $this->connector()->send(new GetEventRequest('5'));

        Saloon::assertSent(function (Request $request) {
            return str_contains($request->resolveEndpoint(), '/events/5');
        });
    }

    private function connector(): RaidHelperConnector
    {
        return new RaidHelperConnector(token: 'test-token', serverId: '111222333444555666');
    }
}
