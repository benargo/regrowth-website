<?php

namespace Tests\Unit\Services\Discord\Resources;

use App\Services\Discord\Resources\GuildMember;
use App\Services\Discord\Resources\User;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

class GuildMemberTest extends TestCase
{
    #[Test]
    public function it_constructs_from_empty_payload(): void
    {
        $member = GuildMember::from([]);

        $this->assertNull($member->user);
        $this->assertNull($member->nick);
        $this->assertNull($member->avatar);
        $this->assertNull($member->banner);
        $this->assertSame([], $member->roles);
        $this->assertNull($member->joined_at);
        $this->assertNull($member->premium_since);
        $this->assertFalse($member->deaf);
        $this->assertFalse($member->mute);
        $this->assertSame(0, $member->flags);
        $this->assertNull($member->pending);
        $this->assertNull($member->permissions);
        $this->assertNull($member->communication_disabled_until);
        $this->assertNull($member->avatar_decoration_data);
        $this->assertNull($member->collectibles);
    }

    #[Test]
    public function it_constructs_from_the_discord_example_payload(): void
    {
        $member = GuildMember::from([
            'user' => ['id' => '1', 'username' => 'test', 'discriminator' => '0000'],
            'nick' => 'NOT API SUPPORT',
            'avatar' => null,
            'banner' => null,
            'roles' => [],
            'joined_at' => '2015-04-26T06:26:56.936000+00:00',
            'deaf' => false,
            'mute' => false,
        ]);

        $this->assertInstanceOf(User::class, $member->user);
        $this->assertSame('NOT API SUPPORT', $member->nick);
        $this->assertNull($member->avatar);
        $this->assertNull($member->banner);
        $this->assertSame([], $member->roles);
        $this->assertSame('2015-04-26T06:26:56.936000+00:00', $member->joined_at);
        $this->assertFalse($member->deaf);
        $this->assertFalse($member->mute);
    }

    #[Test]
    public function it_stores_all_scalar_fields(): void
    {
        $member = GuildMember::from([
            'user' => ['id' => '123', 'username' => 'Thrall', 'discriminator' => '0001'],
            'nick' => 'Warchief',
            'avatar' => 'avatar_hash',
            'banner' => 'banner_hash',
            'roles' => ['111', '222'],
            'joined_at' => '2024-01-01T00:00:00Z',
            'premium_since' => '2024-06-01T00:00:00Z',
            'deaf' => true,
            'mute' => true,
            'flags' => 2,
            'pending' => false,
            'permissions' => '8',
            'communication_disabled_until' => '2024-12-31T23:59:59Z',
        ]);

        $this->assertInstanceOf(User::class, $member->user);
        $this->assertSame('123', $member->user->id);
        $this->assertSame('Thrall', $member->user->username);
        $this->assertSame('Warchief', $member->nick);
        $this->assertSame('avatar_hash', $member->avatar);
        $this->assertSame('banner_hash', $member->banner);
        $this->assertSame(['111', '222'], $member->roles);
        $this->assertSame('2024-01-01T00:00:00Z', $member->joined_at);
        $this->assertSame('2024-06-01T00:00:00Z', $member->premium_since);
        $this->assertTrue($member->deaf);
        $this->assertTrue($member->mute);
        $this->assertSame(2, $member->flags);
        $this->assertFalse($member->pending);
        $this->assertSame('8', $member->permissions);
        $this->assertSame('2024-12-31T23:59:59Z', $member->communication_disabled_until);
    }

    #[Test]
    public function it_stores_avatar_decoration_data_and_collectibles(): void
    {
        $decoration = ['asset' => 'decoration_hash', 'sku_id' => '999'];
        $collectibles = ['nameplate' => ['asset' => 'nameplate_hash', 'label' => 'Gold']];

        $member = GuildMember::from([
            'avatar_decoration_data' => $decoration,
            'collectibles' => $collectibles,
        ]);

        $this->assertSame($decoration, $member->avatar_decoration_data);
        $this->assertSame($collectibles, $member->collectibles);
    }

    #[Test]
    public function all_properties_are_readonly(): void
    {
        $member = GuildMember::from([]);
        $reflection = new ReflectionClass($member);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== GuildMember::class) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly."
            );
        }
    }
}
