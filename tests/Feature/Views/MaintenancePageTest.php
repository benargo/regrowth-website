<?php

namespace Tests\Feature\Views;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class MaintenancePageTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->app->maintenanceMode()->deactivate();

        parent::tearDown();
    }

    #[Test]
    public function it_renders_the_branded_maintenance_page_with_a_503(): void
    {
        $this->app->maintenanceMode()->activate([]);

        $this->get('/')
            ->assertServiceUnavailable()
            ->assertSee('Jaina&rsquo;s running the updates', false)
            ->assertSee('images/sticker_jaina_maintenance_503.webp')
            ->assertSee('Back online soon');
    }

    #[Test]
    public function it_does_not_show_the_realm_tagline(): void
    {
        config(['services.blizzard.realm.name' => 'Thunderstrike']);
        $this->app->maintenanceMode()->activate([]);

        $this->get('/')
            ->assertServiceUnavailable()
            ->assertDontSee('Thunderstrike');
    }

    #[Test]
    public function it_shows_the_expected_return_time_when_a_retry_is_set(): void
    {
        $this->app->maintenanceMode()->activate(['retry' => 600]);

        $this->get('/')
            ->assertServiceUnavailable()
            ->assertHeader('Retry-After', 600)
            ->assertSee('Expected back in about 10 minutes');
    }
}
