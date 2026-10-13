<?php

namespace Tests\Unit\Http\Integrations\Discord\Requests\Messages;

use App\Http\Integrations\Discord\Data\Messages\MessageData;
use App\Http\Integrations\Discord\Exceptions\MessageNotFoundException;
use App\Http\Integrations\Discord\Requests\Messages\GetMessageRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Discord\DiscordTestCase;

#[Group('discord-integration')]
class GetMessageRequestTest extends DiscordTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_gets_the_message(): void
    {
        Saloon::fake([GetMessageRequest::class => MockResponse::make([
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
        ])]);

        $message = $this->makeConnector()->send(new GetMessageRequest('111111111111111111', '222222222222222222'))->dto();

        $this->assertInstanceOf(MessageData::class, $message);
        $this->assertSame('111111111111111111', $message->channelId);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetMessageRequest
            && $request->resolveEndpoint() === '/channels/111111111111111111/messages/222222222222222222');
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_message_not_found_for_unknown_message(): void
    {
        Saloon::fake([GetMessageRequest::class => MockResponse::make(['message' => 'Unknown Message', 'code' => 10008], 404)]);

        $this->expectException(MessageNotFoundException::class);

        $this->makeConnector()->send(new GetMessageRequest('111111111111111111', '222222222222222222'));
    }
}
