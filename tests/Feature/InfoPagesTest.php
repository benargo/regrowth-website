<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('platform')]
class InfoPagesTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function the_about_page_renders(): void
    {
        $this->get('/about')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Info/About'));
    }
}
