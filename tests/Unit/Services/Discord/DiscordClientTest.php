<?php

namespace Tests\Unit\Services\Discord;

use App\Services\Discord\DiscordClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordClientTest extends TestCase
{
    private DiscordClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->client = new DiscordClient('test-bot-token');
    }

    #[Test]
    public function it_sends_the_bot_authorization_header_on_get_requests(): void
    {
        $this->client->get('/channels/123');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bot test-bot-token');
        });
    }

    #[Test]
    public function it_sends_get_requests_to_the_correct_url(): void
    {
        $this->client->get('/channels/123');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://discord.com/api/v10/channels/123';
        });
    }

    #[Test]
    public function it_sends_query_parameters_with_get_requests(): void
    {
        $this->client->get('/guilds/123/members', ['limit' => 100, 'after' => '456']);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), 'limit=100')
                && str_contains($request->url(), 'after=456');
        });
    }

    #[Test]
    public function it_sends_the_bot_authorization_header_on_post_requests(): void
    {
        $this->client->post('/channels/123/messages', ['content' => 'Hello']);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bot test-bot-token');
        });
    }

    #[Test]
    public function it_sends_post_requests_to_the_correct_url_with_body(): void
    {
        $this->client->post('/channels/123/messages', ['content' => 'Hello']);

        Http::assertSent(function (Request $request) {
            return $request->method('POST')
                && $request->url() === 'https://discord.com/api/v10/channels/123/messages'
                && $request->data()['content'] === 'Hello';
        });
    }

    #[Test]
    public function it_sends_the_bot_authorization_header_on_patch_requests(): void
    {
        $this->client->patch('/channels/123', ['name' => 'new-name']);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bot test-bot-token');
        });
    }

    #[Test]
    public function it_sends_patch_requests_to_the_correct_url_with_body(): void
    {
        $this->client->patch('/channels/123', ['name' => 'new-name']);

        Http::assertSent(function (Request $request) {
            return $request->method('PATCH')
                && $request->url() === 'https://discord.com/api/v10/channels/123'
                && $request->data()['name'] === 'new-name';
        });
    }

    #[Test]
    public function it_sends_the_bot_authorization_header_on_delete_requests(): void
    {
        $this->client->delete('/channels/123');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bot test-bot-token');
        });
    }

    #[Test]
    public function it_sends_delete_requests_to_the_correct_url(): void
    {
        $this->client->delete('/channels/123');

        Http::assertSent(function (Request $request) {
            return $request->method('DELETE')
                && $request->url() === 'https://discord.com/api/v10/channels/123';
        });
    }
}
