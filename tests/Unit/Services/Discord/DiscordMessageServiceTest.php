<?php

namespace Tests\Unit\Services\Discord;

use App\Services\Discord\DiscordMessageService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordMessageServiceTest extends TestCase
{
    private DiscordMessageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DiscordMessageService('test_bot_token');
    }

    #[Test]
    public function it_creates_message_and_returns_id(): void
    {
        Http::fake([
            'discord.com/api/v10/channels/999/messages' => Http::response([
                'id' => '123456',
                'content' => 'Hello',
            ], 200),
        ]);

        $messageId = $this->service->createMessage('999', ['content' => 'Hello']);

        $this->assertSame('123456', $messageId);
    }

    #[Test]
    public function it_logs_and_rethrows_on_create_failure(): void
    {
        Http::fake([
            'discord.com/api/v10/channels/999/messages' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to create Discord message', \Mockery::on(function (array $context) {
                return $context['channel_id'] === '999'
                    && $context['error'] === 'Connection refused';
            }));

        $this->expectException(ConnectionException::class);

        $this->service->createMessage('999', ['content' => 'Hello']);
    }

    #[Test]
    public function it_updates_message_successfully(): void
    {
        Http::fake([
            'discord.com/api/v10/channels/999/messages/123' => Http::response([
                'id' => '123',
                'content' => 'Updated',
            ], 200),
        ]);

        $this->service->updateMessage('999', '123', ['content' => 'Updated']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'channels/999/messages/123')
                && $request->method() === 'PATCH';
        });
    }

    #[Test]
    public function it_logs_and_rethrows_on_update_failure(): void
    {
        Http::fake([
            'discord.com/api/v10/channels/999/messages/123' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to update Discord message', \Mockery::on(function (array $context) {
                return $context['channel_id'] === '999'
                    && $context['message_id'] === '123'
                    && $context['error'] === 'Connection refused';
            }));

        $this->expectException(ConnectionException::class);

        $this->service->updateMessage('999', '123', ['content' => 'Updated']);
    }

    #[Test]
    public function it_deletes_message_successfully(): void
    {
        Http::fake([
            'discord.com/api/v10/channels/999/messages/123' => Http::response(null, 204),
        ]);

        $this->service->deleteMessage('999', '123');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'channels/999/messages/123')
                && $request->method() === 'DELETE';
        });
    }

    #[Test]
    public function it_logs_warning_on_delete_failure_but_does_not_throw(): void
    {
        Http::fake([
            'discord.com/api/v10/channels/999/messages/123' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('Failed to delete Discord message', \Mockery::on(function (array $context) {
                return $context['channel_id'] === '999'
                    && $context['message_id'] === '123'
                    && $context['error'] === 'Connection refused';
            }));

        // Should NOT throw - exception is caught and logged as warning
        $this->service->deleteMessage('999', '123');
    }

    #[Test]
    public function it_sends_correct_authorization_header(): void
    {
        Http::fake([
            'discord.com/api/v10/channels/999/messages' => Http::response(['id' => '1'], 200),
        ]);

        $this->service->createMessage('999', ['content' => 'Test']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bot test_bot_token');
        });
    }
}
