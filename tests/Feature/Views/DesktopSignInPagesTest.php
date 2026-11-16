<?php

namespace Tests\Feature\Views;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class DesktopSignInPagesTest extends TestCase
{
    #[Test]
    public function it_renders_the_branded_signed_in_page_for_a_guest(): void
    {
        $this->get(route('desktop.signed-in'))
            ->assertOk()
            ->assertSee('Jaina&rsquo;s signed you in', false)
            ->assertSee('images/sticker_jaina_maintenance_503.webp')
            ->assertSee('Signed in. Return to Regrowth Desktop.');
    }

    #[Test]
    public function it_does_not_offer_any_actions_on_the_signed_in_page(): void
    {
        $this->get(route('desktop.signed-in'))
            ->assertOk()
            ->assertDontSee(route('discord'))
            ->assertDontSee('Go to the home page');
    }

    #[Test]
    public function it_does_not_show_the_realm_tagline_on_the_signed_in_page(): void
    {
        config(['services.blizzard.realm.name' => 'Thunderstrike']);

        $this->get(route('desktop.signed-in'))
            ->assertOk()
            ->assertDontSee('Thunderstrike');
    }

    #[Test]
    public function it_renders_the_branded_sign_in_failed_page_for_a_guest(): void
    {
        $this->get(route('desktop.sign-in-failed'))
            ->assertOk()
            ->assertSee('Jaina couldn&rsquo;t sign you in', false)
            ->assertSee('images/sticker_jaina_maintenance_503.webp')
            ->assertSee('Sign-in didn&rsquo;t finish. Try again from Regrowth Desktop.', false);
    }

    #[Test]
    public function it_links_to_discord_on_the_sign_in_failed_page(): void
    {
        $this->get(route('desktop.sign-in-failed'))
            ->assertOk()
            ->assertSee(route('discord'))
            ->assertSee('Ask on Discord')
            ->assertDontSee('Go to the home page');
    }
}
