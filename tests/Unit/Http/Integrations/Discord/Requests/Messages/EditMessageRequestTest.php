<?php

namespace Tests\Unit\Http\Integrations\Discord\Requests\Messages;

use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Http\Integrations\Discord\Requests\Messages\EditMessageRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Discord\DiscordTestCase;

#[Group('discord-integration')]
class EditMessageRequestTest extends DiscordTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_patches_the_message_endpoint(): void
    {
        Saloon::fake([EditMessageRequest::class => MockResponse::make([
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

        $this->makeConnector()
            ->send(new EditMessageRequest('111111111111111111', '222222222222222222', MessagePayloadData::from(['content' => 'Edited'])));

        Saloon::assertSent(fn (Request $request): bool => $request instanceof EditMessageRequest
            && $request->getMethod() === Method::PATCH
            && $request->resolveEndpoint() === '/channels/111111111111111111/messages/222222222222222222'
            && $request->body()->all() === ['content' => 'Edited']);
    }

    #[Test]
    #[Group('contract')]
    public function it_drops_create_only_keys_from_the_body(): void
    {
        $payload = MessagePayloadData::from([
            'content' => 'Edited',
            'nonce' => 'n',
            'tts' => true,
            'embeds' => [['title' => 'Embed']],
            'message_reference' => ['message_id' => '333333333333333333'],
            'sticker_ids' => ['444444444444444444'],
            'attachments' => [['id' => '555555555555555555', 'filename' => 'file.png', 'description' => 'A screenshot']],
            'flags' => 4,
            'enforce_nonce' => true,
        ]);

        $body = (new EditMessageRequest('111111111111111111', '222222222222222222', $payload))->body()->all();

        $this->assertSame([
            'content' => 'Edited',
            'embeds' => [['title' => 'Embed']],
            'attachments' => [['id' => '555555555555555555', 'filename' => 'file.png', 'description' => 'A screenshot']],
            'flags' => 4,
        ], $body);
    }

    #[Test]
    #[Group('contract')]
    public function it_omits_absent_fields_rather_than_sending_null(): void
    {
        $payload = MessagePayloadData::from(['embeds' => [['title' => 'Embed only']]]);

        $body = (new EditMessageRequest('111111111111111111', '222222222222222222', $payload))->body()->all();

        $this->assertSame(['embeds' => [['title' => 'Embed only']]], $body);
        $this->assertArrayNotHasKey('content', $body);
        $this->assertArrayNotHasKey('attachments', $body);
    }
}
