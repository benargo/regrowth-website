<?php

namespace Tests\Feature\Addons;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class IndexTest extends TestCase
{
    use RefreshDatabase;

    // ==================== index ====================

    #[Group('happy-path')]
    #[Test]
    public function index_is_public(): void
    {
        $this->get(route('addons.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Addons/Index'));
    }
}
