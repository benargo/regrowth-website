<?php

namespace Tests\Unit\Http\Integrations\Discord\Requests\Messages;

use App\Http\Integrations\Discord\Requests\Messages\DeleteMessageRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Discord\DiscordTestCase;

#[Group('discord-integration')]
class DeleteMessageRequestTest extends DiscordTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_deletes_the_message(): void
    {
        Saloon::fake([DeleteMessageRequest::class => MockResponse::make('', 204)]);

        $this->makeConnector()->send(new DeleteMessageRequest('111111111111111111', '222222222222222222'));

        Saloon::assertSent(fn (Request $request): bool => $request instanceof DeleteMessageRequest
            && $request->getMethod() === Method::DELETE
            && $request->resolveEndpoint() === '/channels/111111111111111111/messages/222222222222222222');
    }
}
