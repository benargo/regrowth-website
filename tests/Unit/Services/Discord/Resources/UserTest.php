<?php

namespace Tests\Unit\Services\Discord\Resources;

use App\Services\Discord\Resources\User;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

class UserTest extends TestCase
{
    #[Test]
    public function it_constructs_from_minimal_required_fields(): void
    {
        $user = User::from([
            'id' => '80351110224678912',
            'username' => 'Nelly',
            'discriminator' => '1337',
        ]);

        $this->assertSame('80351110224678912', $user->id);
        $this->assertSame('Nelly', $user->username);
        $this->assertSame('1337', $user->discriminator);
        $this->assertNull($user->global_name);
        $this->assertNull($user->avatar);
        $this->assertNull($user->bot);
        $this->assertNull($user->system);
        $this->assertNull($user->mfa_enabled);
        $this->assertNull($user->banner);
        $this->assertNull($user->accent_color);
        $this->assertNull($user->locale);
        $this->assertNull($user->verified);
        $this->assertNull($user->email);
        $this->assertSame(0, $user->flags);
        $this->assertNull($user->premium_type);
        $this->assertSame(0, $user->public_flags);
        $this->assertNull($user->avatar_decoration_data);
        $this->assertNull($user->collectibles);
        $this->assertNull($user->primary_guild);
    }

    #[Test]
    public function it_constructs_from_the_discord_example_payload(): void
    {
        $user = User::from([
            'id' => '80351110224678912',
            'username' => 'Nelly',
            'discriminator' => '1337',
            'global_name' => 'Nelly',
            'avatar' => '8342729096ea3675442027381ff50dfe',
            'verified' => true,
            'email' => 'nelly@discord.com',
            'flags' => 64,
            'premium_type' => 1,
            'public_flags' => 64,
        ]);

        $this->assertSame('80351110224678912', $user->id);
        $this->assertSame('Nelly', $user->username);
        $this->assertSame('1337', $user->discriminator);
        $this->assertSame('Nelly', $user->global_name);
        $this->assertSame('8342729096ea3675442027381ff50dfe', $user->avatar);
        $this->assertTrue($user->verified);
        $this->assertSame('nelly@discord.com', $user->email);
        $this->assertSame(64, $user->flags);
        $this->assertSame(1, $user->premium_type);
        $this->assertSame(64, $user->public_flags);
    }

    #[Test]
    public function it_stores_all_optional_scalar_fields(): void
    {
        $user = User::from([
            'id' => '1234',
            'username' => 'Thrall',
            'discriminator' => '0001',
            'global_name' => 'Warchief',
            'avatar' => 'avatar_hash',
            'bot' => false,
            'system' => false,
            'mfa_enabled' => true,
            'banner' => 'banner_hash',
            'accent_color' => 16711680,
            'locale' => 'en-US',
            'verified' => true,
            'email' => 'thrall@orgrimmar.net',
            'flags' => 128,
            'premium_type' => 2,
            'public_flags' => 128,
        ]);

        $this->assertSame('1234', $user->id);
        $this->assertSame('Thrall', $user->username);
        $this->assertSame('0001', $user->discriminator);
        $this->assertSame('Warchief', $user->global_name);
        $this->assertSame('avatar_hash', $user->avatar);
        $this->assertFalse($user->bot);
        $this->assertFalse($user->system);
        $this->assertTrue($user->mfa_enabled);
        $this->assertSame('banner_hash', $user->banner);
        $this->assertSame(16711680, $user->accent_color);
        $this->assertSame('en-US', $user->locale);
        $this->assertTrue($user->verified);
        $this->assertSame('thrall@orgrimmar.net', $user->email);
        $this->assertSame(128, $user->flags);
        $this->assertSame(2, $user->premium_type);
        $this->assertSame(128, $user->public_flags);
    }

    #[Test]
    public function it_stores_nested_object_fields(): void
    {
        $decoration = ['asset' => 'decoration_hash', 'sku_id' => '999'];
        $collectibles = ['nameplate' => ['sku_id' => '888', 'asset' => 'nameplate_hash', 'label' => 'Gold', 'palette' => []]];
        $primaryGuild = ['identity_guild_id' => '555', 'identity_enabled' => true, 'tag' => 'ORG', 'badge' => 'badge_hash'];

        $user = User::from([
            'id' => '1234',
            'username' => 'Thrall',
            'discriminator' => '0001',
            'avatar_decoration_data' => $decoration,
            'collectibles' => $collectibles,
            'primary_guild' => $primaryGuild,
        ]);

        $this->assertSame($decoration, $user->avatar_decoration_data);
        $this->assertSame($collectibles, $user->collectibles);
        $this->assertSame($primaryGuild, $user->primary_guild);
    }

    #[Test]
    public function it_handles_nullable_string_fields_being_null(): void
    {
        $user = User::from([
            'id' => '1234',
            'username' => 'Thrall',
            'discriminator' => '0001',
            'global_name' => null,
            'avatar' => null,
            'banner' => null,
            'email' => null,
        ]);

        $this->assertNull($user->global_name);
        $this->assertNull($user->avatar);
        $this->assertNull($user->banner);
        $this->assertNull($user->email);
    }

    #[Test]
    public function it_marks_a_bot_user(): void
    {
        $user = User::from([
            'id' => '5678',
            'username' => 'BotUser',
            'discriminator' => '0000',
            'bot' => true,
        ]);

        $this->assertTrue($user->bot);
    }

    #[Test]
    public function all_properties_are_readonly(): void
    {
        $user = User::from([
            'id' => '1234',
            'username' => 'Thrall',
            'discriminator' => '0001',
        ]);
        $reflection = new ReflectionClass($user);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== User::class) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly."
            );
        }
    }
}
