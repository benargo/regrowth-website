<?php

namespace Tests\Feature\Views;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class NotFoundPageTest extends TestCase
{
    #[Test]
    public function it_renders_the_branded_not_found_page_with_a_404(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('Jaina can&rsquo;t find this page', false)
            ->assertSee('images/sticker_jaina_404.webp')
            ->assertSee('/this-page-does-not-exist');
    }

    #[Test]
    public function it_escapes_the_requested_path(): void
    {
        $this->get('/<script>alert(1)</script>')
            ->assertNotFound()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    #[Test]
    public function it_does_not_show_the_realm_tagline(): void
    {
        config(['services.blizzard.realm.name' => 'Thunderstrike']);

        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertDontSee('Thunderstrike');
    }

    #[Test]
    public function it_returns_json_when_the_client_expects_json(): void
    {
        $this->getJson('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertJsonStructure(['message'])
            ->assertDontSee('<html', false);
    }

    #[Test]
    public function it_returns_json_for_unknown_api_routes_without_an_accept_header(): void
    {
        $this->get('/api/this-endpoint-does-not-exist')
            ->assertNotFound()
            ->assertJsonStructure(['message'])
            ->assertDontSee('<html', false);
    }
}
