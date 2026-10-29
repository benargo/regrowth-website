<?php

namespace Tests\Feature\Views;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class GonePageTest extends TestCase
{
    #[Test]
    public function it_renders_the_branded_gone_page_with_a_410(): void
    {
        $this->post('/manage/addon/settings/councillors')
            ->assertGone()
            ->assertSee('Jaina found this door boarded up')
            ->assertSee('images/sticker_jaina_410.webp')
            ->assertSee('/manage/addon/settings/councillors');
    }

    #[Test]
    public function it_does_not_show_the_realm_tagline(): void
    {
        config(['services.blizzard.realm.name' => 'Thunderstrike']);

        $this->post('/manage/addon/settings/councillors')
            ->assertGone()
            ->assertDontSee('Thunderstrike');
    }

    #[Test]
    public function it_returns_json_when_the_client_expects_json(): void
    {
        $this->postJson('/manage/addon/settings/councillors')
            ->assertGone()
            ->assertJsonStructure(['message'])
            ->assertDontSee('<html', false);
    }
}
