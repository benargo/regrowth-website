<?php

namespace Tests\Unit\Services\RaidHelper;

use App\Services\RaidHelper\Contracts\PayloadContract;
use App\Services\RaidHelper\RaidHelperClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RaidHelperClientTest extends TestCase
{
    private RaidHelperClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->client = new RaidHelperClient('test-api-token');
    }

    #[Test]
    public function it_sends_the_authorization_header_on_get_requests(): void
    {
        $this->client->get('/servers/123/events');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'test-api-token');
        });
    }

    #[Test]
    public function it_sends_get_requests_to_the_correct_url(): void
    {
        $this->client->get('/servers/123/events');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://raid-helper.xyz/api/v4/servers/123/events';
        });
    }

    #[Test]
    public function it_sends_additional_headers_with_get_requests(): void
    {
        $this->client->get('/servers/123/events', ['X-Custom-Header' => 'custom-value']);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('X-Custom-Header', 'custom-value');
        });
    }

    #[Test]
    public function it_sends_the_authorization_header_on_post_requests(): void
    {
        $this->client->post('/servers/123/events');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'test-api-token');
        });
    }

    #[Test]
    public function it_sends_post_requests_to_the_correct_url(): void
    {
        $this->client->post('/servers/123/events');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://raid-helper.xyz/api/v4/servers/123/events';
        });
    }

    #[Test]
    public function it_sends_a_payload_with_post_requests(): void
    {
        $payload = new class implements PayloadContract
        {
            public function toArray(): array
            {
                return ['title' => 'Raid Night'];
            }
        };

        $this->client->post('/servers/123/events', payload: $payload);

        Http::assertSent(function (Request $request) {
            return $request->data()['title'] === 'Raid Night';
        });
    }

    #[Test]
    public function it_sends_an_empty_body_when_no_payload_is_provided_on_post_requests(): void
    {
        $this->client->post('/servers/123/events');

        Http::assertSent(function (Request $request) {
            return $request->data() === [];
        });
    }

    #[Test]
    public function it_sends_the_authorization_header_on_patch_requests(): void
    {
        $this->client->patch('/events/456');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'test-api-token');
        });
    }

    #[Test]
    public function it_sends_patch_requests_to_the_correct_url(): void
    {
        $this->client->patch('/events/456');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'PATCH'
                && $request->url() === 'https://raid-helper.xyz/api/v4/events/456';
        });
    }

    #[Test]
    public function it_sends_a_payload_with_patch_requests(): void
    {
        $payload = new class implements PayloadContract
        {
            public function toArray(): array
            {
                return ['title' => 'Updated Raid'];
            }
        };

        $this->client->patch('/events/456', payload: $payload);

        Http::assertSent(function (Request $request) {
            return $request->data()['title'] === 'Updated Raid';
        });
    }

    #[Test]
    public function it_sends_the_authorization_header_on_delete_requests(): void
    {
        $this->client->delete('/events/456');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'test-api-token');
        });
    }

    #[Test]
    public function it_sends_delete_requests_to_the_correct_url(): void
    {
        $this->client->delete('/events/456');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'DELETE'
                && $request->url() === 'https://raid-helper.xyz/api/v4/events/456';
        });
    }

    #[Test]
    public function it_strips_leading_slashes_from_endpoints(): void
    {
        $this->client->get('servers/123/events');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://raid-helper.xyz/api/v4/servers/123/events';
        });
    }

    #[Test]
    public function it_sends_the_content_type_json_header_on_all_requests(): void
    {
        $this->client->get('/servers/123/events');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Content-Type', 'application/json');
        });
    }

    #[Test]
    public function it_merges_additional_headers_with_default_headers(): void
    {
        $this->client->post('/servers/123/events', headers: ['X-Guild-Id' => '999']);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'test-api-token')
                && $request->hasHeader('X-Guild-Id', '999');
        });
    }
}
