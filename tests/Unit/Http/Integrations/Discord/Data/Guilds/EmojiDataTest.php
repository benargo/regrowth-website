<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\EmojiData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('discord-integration')]
class EmojiDataTest extends TestCase
{
    #[Test]
    public function it_maps_a_guild_emoji(): void
    {
        $emoji = EmojiData::from([
            'id' => '41771983429993937',
            'name' => 'regrowth',
            'roles' => ['41771983429993000'],
            'user' => ['id' => '80351110224678912', 'username' => 'Nelly', 'discriminator' => '1337', 'avatar' => null],
            'require_colons' => true,
            'managed' => false,
            'animated' => true,
            'available' => false,
        ]);

        $this->assertSame('41771983429993937', $emoji->id);
        $this->assertSame('regrowth', $emoji->name);
        $this->assertSame(['41771983429993000'], $emoji->roles);
        $this->assertSame('80351110224678912', $emoji->user->id);
        $this->assertTrue($emoji->requireColons);
        $this->assertFalse($emoji->managed);
        $this->assertTrue($emoji->animated);
        $this->assertFalse($emoji->available);
    }

    #[Test]
    public function it_leaves_omitted_fields_optional(): void
    {
        $emoji = EmojiData::from(['id' => '1', 'name' => 'pog']);

        $this->assertInstanceOf(Optional::class, $emoji->animated);
        $this->assertInstanceOf(Optional::class, $emoji->available);
        $this->assertInstanceOf(Optional::class, $emoji->roles);
        $this->assertInstanceOf(Optional::class, $emoji->user);
    }
}
