<?php

namespace Tests\Feature;

use App\Models\Character;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('platform')]
class DeprecatedRoutesTest extends DashboardTestCase
{
    #[Test]
    #[Group('deprecated')]
    public function add_councillor_endpoint_is_gone(): void
    {
        $response = $this->actingAs($this->officer)
            ->post('/addon/settings/councillors', [
                'character_name' => 'Anyone',
            ]);

        $response->assertGone();
    }

    #[Test]
    #[Group('deprecated')]
    public function remove_councillor_endpoint_is_gone(): void
    {
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($this->officer)
            ->delete("/addon/settings/councillors/{$character->id}");

        $response->assertGone();
    }
}
