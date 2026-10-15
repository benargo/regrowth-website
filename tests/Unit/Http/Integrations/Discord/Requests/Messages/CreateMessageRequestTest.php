<?php

namespace Tests\Unit\Http\Integrations\Discord\Requests\Messages;

use App\Http\Integrations\Discord\Data\Messages\MessageData;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Http\Integrations\Discord\Requests\Messages\CreateMessageRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Discord\DiscordTestCase;

#[Group('discord-integration')]
class CreateMessageRequestTest extends DiscordTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_posts_the_payload_as_a_snake_case_json_body(): void
    {
        Saloon::fake([CreateMessageRequest::class => MockResponse::make($this->messageResponse())]);

        $payload = MessagePayloadData::from([
            'content' => 'Hello',
            'embeds' => [['title' => 'Embed', 'footer' => ['text' => 'f', 'icon_url' => 'https://cdn.example.test/a.png']]],
            'message_reference' => ['message_id' => '333333333333333333'],
            'sticker_ids' => ['444444444444444444'],
        ]);

        $message = $this->makeConnector()->send(new CreateMessageRequest('111111111111111111', $payload))->dto();

        $this->assertInstanceOf(MessageData::class, $message);
        $this->assertSame('222222222222222222', $message->id);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof CreateMessageRequest
            && $request->getMethod() === Method::POST
            && $request->resolveEndpoint() === '/channels/111111111111111111/messages'
            && $request->body()->all() === [
                'content' => 'Hello',
                'embeds' => [['title' => 'Embed', 'footer' => ['text' => 'f', 'icon_url' => 'https://cdn.example.test/a.png']]],
                'message_reference' => ['message_id' => '333333333333333333'],
                'sticker_ids' => ['444444444444444444'],
            ]);
    }

    #[Test]
    #[Group('contract')]
    public function it_sends_a_json_content_type(): void
    {
        Saloon::fake([CreateMessageRequest::class => MockResponse::make($this->messageResponse())]);

        $response = $this->makeConnector()->send(new CreateMessageRequest('111111111111111111', MessagePayloadData::from(['content' => 'Hello'])));

        $this->assertSame('application/json', $response->getPendingRequest()->headers()->get('Content-Type'));
    }

    // ==================== helpers ====================

    /**
     * @return array<string, mixed>
     */
    private function messageResponse(): array
    {
        return [
            'id' => '222222222222222222',
            'channel_id' => '111111111111111111',
            'author' => ['id' => '333333333333333333', 'username' => 'Regrowth', 'discriminator' => '0'],
            'content' => '',
            'timestamp' => '2026-10-13T12:00:00+00:00',
            'edited_timestamp' => null,
            'tts' => false,
            'mention_everyone' => false,
            'mentions' => [],
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => 0,
        ];
    }
}
