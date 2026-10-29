<?php

namespace Tests\Feature\Views;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class ForbiddenPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/forbidden-test-page', fn () => abort(403));
    }

    #[Test]
    public function it_renders_the_branded_forbidden_page_with_a_403(): void
    {
        $this->get('/forbidden-test-page')
            ->assertForbidden()
            ->assertSee('Jaina can&rsquo;t let you in', false)
            ->assertSee('images/sticker_jaina_403.webp')
            ->assertSee('/forbidden-test-page');
    }

    #[Test]
    public function it_does_not_show_the_realm_tagline(): void
    {
        config(['services.blizzard.realm.name' => 'Thunderstrike']);

        $this->get('/forbidden-test-page')
            ->assertForbidden()
            ->assertDontSee('Thunderstrike');
    }

    #[Test]
    public function it_returns_json_when_the_client_expects_json(): void
    {
        $this->getJson('/forbidden-test-page')
            ->assertForbidden()
            ->assertJsonStructure(['message'])
            ->assertDontSee('<html', false);
    }
}
