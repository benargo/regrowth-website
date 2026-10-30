<?php

namespace Tests\Unit\Data\Discord;

use App\Data\Discord\MentionRoleData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class MentionRoleDataTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_serialises_the_id_and_name(): void
    {
        $role = new MentionRoleData(id: '1234567890123456789', name: 'Raider');

        $this->assertSame([
            'id' => '1234567890123456789',
            'name' => 'Raider',
        ], $role->toArray());
    }
}
