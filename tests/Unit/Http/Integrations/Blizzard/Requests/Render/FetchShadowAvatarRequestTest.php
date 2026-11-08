<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Render;

use App\Enums\Gender;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchShadowAvatarRequest;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

#[Group('blizzard-integration')]
class FetchShadowAvatarRequestTest extends TestCase
{
    #[Test]
    public function it_is_a_get_request(): void
    {
        $this->assertSame(Method::GET, (new FetchShadowAvatarRequest(6, Gender::MALE))->getMethod());
    }

    #[Test]
    public function it_requests_the_race_and_gender_shadow_avatar_in_the_connector_region(): void
    {
        $mock = new MockClient([
            FetchShadowAvatarRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('s3'));
        $connector->withMockClient($mock);

        $response = $connector->send(new FetchShadowAvatarRequest(6, Gender::MALE));

        $this->assertSame('BINARY', $response->body());
        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/shadow/avatar/6-0.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }

    #[Test]
    public function it_uses_the_female_gender_id(): void
    {
        $mock = new MockClient([
            FetchShadowAvatarRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::US, Storage::fake('s3'));
        $connector->withMockClient($mock);

        $connector->send(new FetchShadowAvatarRequest(96, Gender::FEMALE));

        $this->assertSame(
            'https://render.worldofwarcraft.com/us/shadow/avatar/96-1.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }

    #[Test]
    public function it_mirrors_the_response_to_the_shadow_avatar_path(): void
    {
        $disk = Storage::fake('s3');
        $mock = new MockClient([
            FetchShadowAvatarRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, $disk);
        $connector->withMockClient($mock);

        $connector->send(new FetchShadowAvatarRequest(6, Gender::MALE));

        $disk->assertExists('blizzard-cdn/shadow/avatar/6-0.jpg');
    }
}
