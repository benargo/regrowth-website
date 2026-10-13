<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Users;

use App\Http\Integrations\Discord\Data\Users\UserData;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('discord-integration')]
class UserDataTest extends TestCase
{
    #[Test]
    public function user_can_be_constructed_directly(): void
    {
        $user = new UserData(
            id: '80351110224678912',
            username: 'Nelly',
            discriminator: '1337',
            globalName: Optional::create(),
            avatar: Optional::create(),
            bot: Optional::create(),
            system: Optional::create(),
            mfaEnabled: Optional::create(),
            banner: Optional::create(),
            accentColor: Optional::create(),
            locale: Optional::create(),
            verified: Optional::create(),
            email: Optional::create(),
            flags: 0,
            premiumType: Optional::create(),
            publicFlags: 0,
            avatarDecorationData: Optional::create(),
            collectibles: Optional::create(),
            primaryGuild: Optional::create(),
        );

        $this->assertSame('80351110224678912', $user->id);
        $this->assertSame('Nelly', $user->username);
        $this->assertSame('1337', $user->discriminator);
        $this->assertInstanceOf(Optional::class, $user->globalName);
        $this->assertInstanceOf(Optional::class, $user->avatar);
        $this->assertInstanceOf(Optional::class, $user->bot);
        $this->assertInstanceOf(Optional::class, $user->system);
        $this->assertInstanceOf(Optional::class, $user->mfaEnabled);
        $this->assertInstanceOf(Optional::class, $user->banner);
        $this->assertInstanceOf(Optional::class, $user->accentColor);
        $this->assertInstanceOf(Optional::class, $user->locale);
        $this->assertInstanceOf(Optional::class, $user->verified);
        $this->assertInstanceOf(Optional::class, $user->email);
        $this->assertSame(0, $user->flags);
        $this->assertInstanceOf(Optional::class, $user->premiumType);
        $this->assertSame(0, $user->publicFlags);
        $this->assertInstanceOf(Optional::class, $user->avatarDecorationData);
        $this->assertInstanceOf(Optional::class, $user->collectibles);
        $this->assertInstanceOf(Optional::class, $user->primaryGuild);
    }

    #[Test]
    public function it_constructs_from_minimal_required_fields(): void
    {
        $user = UserData::from([
            'id' => '80351110224678912',
            'username' => 'Nelly',
            'discriminator' => '1337',
            'flags' => 0,
            'public_flags' => 0,
        ]);

        $this->assertSame('80351110224678912', $user->id);
        $this->assertSame('Nelly', $user->username);
        $this->assertSame('1337', $user->discriminator);
        $this->assertInstanceOf(Optional::class, $user->globalName);
        $this->assertInstanceOf(Optional::class, $user->avatar);
        $this->assertInstanceOf(Optional::class, $user->bot);
        $this->assertInstanceOf(Optional::class, $user->system);
        $this->assertInstanceOf(Optional::class, $user->mfaEnabled);
        $this->assertInstanceOf(Optional::class, $user->banner);
        $this->assertInstanceOf(Optional::class, $user->accentColor);
        $this->assertInstanceOf(Optional::class, $user->locale);
        $this->assertInstanceOf(Optional::class, $user->verified);
        $this->assertInstanceOf(Optional::class, $user->email);
        $this->assertSame(0, $user->flags);
        $this->assertInstanceOf(Optional::class, $user->premiumType);
        $this->assertSame(0, $user->publicFlags);
        $this->assertInstanceOf(Optional::class, $user->avatarDecorationData);
        $this->assertInstanceOf(Optional::class, $user->collectibles);
        $this->assertInstanceOf(Optional::class, $user->primaryGuild);
    }

    #[Test]
    public function it_constructs_from_the_discord_example_payload(): void
    {
        $user = UserData::from([
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
        $this->assertSame('Nelly', $user->globalName);
        $this->assertSame('8342729096ea3675442027381ff50dfe', $user->avatar);
        $this->assertTrue($user->verified);
        $this->assertSame('nelly@discord.com', $user->email);
        $this->assertSame(64, $user->flags);
        $this->assertSame(1, $user->premiumType);
        $this->assertSame(64, $user->publicFlags);
    }

    #[Test]
    public function it_stores_all_optional_scalar_fields(): void
    {
        $user = UserData::from([
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
        $this->assertSame('Warchief', $user->globalName);
        $this->assertSame('avatar_hash', $user->avatar);
        $this->assertFalse($user->bot);
        $this->assertFalse($user->system);
        $this->assertTrue($user->mfaEnabled);
        $this->assertSame('banner_hash', $user->banner);
        $this->assertSame(16711680, $user->accentColor);
        $this->assertSame('en-US', $user->locale);
        $this->assertTrue($user->verified);
        $this->assertSame('thrall@orgrimmar.net', $user->email);
        $this->assertSame(128, $user->flags);
        $this->assertSame(2, $user->premiumType);
        $this->assertSame(128, $user->publicFlags);
    }

    #[Test]
    public function it_stores_nested_object_fields(): void
    {
        $decoration = ['asset' => 'decoration_hash', 'sku_id' => '999'];
        $collectibles = ['nameplate' => ['sku_id' => '888', 'asset' => 'nameplate_hash', 'label' => 'Gold', 'palette' => []]];
        $primaryGuild = ['identity_guild_id' => '555', 'identity_enabled' => true, 'tag' => 'ORG', 'badge' => 'badge_hash'];

        $user = UserData::from([
            'id' => '1234',
            'username' => 'Thrall',
            'discriminator' => '0001',
            'flags' => 0,
            'public_flags' => 0,
            'avatar_decoration_data' => $decoration,
            'collectibles' => $collectibles,
            'primary_guild' => $primaryGuild,
        ]);

        $this->assertSame($decoration, $user->avatarDecorationData);
        $this->assertSame($collectibles, $user->collectibles);
        $this->assertSame($primaryGuild, $user->primaryGuild);
    }

    #[Test]
    public function it_handles_nullable_string_fields_being_null(): void
    {
        $user = UserData::from([
            'id' => '1234',
            'username' => 'Thrall',
            'discriminator' => '0001',
            'flags' => 0,
            'public_flags' => 0,
            'global_name' => null,
            'avatar' => null,
            'banner' => null,
            'email' => null,
        ]);

        $this->assertNull($user->globalName);
        $this->assertNull($user->avatar);
        $this->assertNull($user->banner);
        $this->assertNull($user->email);
    }

    #[Test]
    public function it_handles_accent_color_being_null(): void
    {
        $user = UserData::from([
            'id' => '1234',
            'username' => 'Thrall',
            'discriminator' => '0001',
            'flags' => 0,
            'public_flags' => 0,
            'accent_color' => null,
        ]);

        $this->assertNull($user->accentColor);
    }

    #[Test]
    public function it_handles_nullable_array_fields_being_null(): void
    {
        $user = UserData::from([
            'id' => '1234',
            'username' => 'Thrall',
            'discriminator' => '0001',
            'flags' => 0,
            'public_flags' => 0,
            'avatar_decoration_data' => null,
            'collectibles' => null,
            'primary_guild' => null,
        ]);

        $this->assertNull($user->avatarDecorationData);
        $this->assertNull($user->collectibles);
        $this->assertNull($user->primaryGuild);
    }

    #[Test]
    public function it_treats_missing_flag_fields_as_optional(): void
    {
        $user = UserData::from([
            'id' => '1234',
            'username' => 'Thrall',
            'discriminator' => '0001',
        ]);

        $this->assertInstanceOf(Optional::class, $user->flags);
        $this->assertInstanceOf(Optional::class, $user->publicFlags);
    }

    #[Test]
    public function it_marks_a_bot_user(): void
    {
        $user = UserData::from([
            'id' => '5678',
            'username' => 'BotUser',
            'discriminator' => '0000',
            'flags' => 0,
            'public_flags' => 0,
            'bot' => true,
        ]);

        $this->assertTrue($user->bot);
    }

    #[Test]
    public function all_properties_are_readonly(): void
    {
        $user = UserData::from([
            'id' => '1234',
            'username' => 'Thrall',
            'discriminator' => '0001',
            'flags' => 0,
            'public_flags' => 0,
        ]);
        $reflection = new ReflectionClass($user);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== UserData::class) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly."
            );
        }
    }

    // ==================== toArray ====================

    #[Test]
    public function it_maps_snake_case_keys_to_camel_case_properties_and_back(): void
    {
        $user = UserData::from([
            'id' => '111111111111111111',
            'username' => 'thrall',
            'discriminator' => '0',
            'global_name' => 'Thrall',
            'mfa_enabled' => true,
            'flags' => 0,
            'public_flags' => 64,
        ]);

        $this->assertSame('Thrall', $user->globalName);
        $this->assertTrue($user->mfaEnabled);
        $this->assertSame(64, $user->publicFlags);

        $array = $user->toArray();

        $this->assertSame('Thrall', $array['global_name']);
        $this->assertSame(64, $array['public_flags']);
        $this->assertArrayNotHasKey('globalName', $array);
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('nonNullableOptionalFields')]
    public function it_rejects_null_for_fields_that_can_only_be_absent(string $field): void
    {
        $this->expectException(ValidationException::class);

        UserData::validate([...['id' => '80351110224678912', 'username' => 'Nelly', 'discriminator' => '1337', 'flags' => 0, 'public_flags' => 0], $field => null]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonNullableOptionalFields(): array
    {
        return collect(['bot', 'system', 'mfa_enabled', 'locale', 'verified', 'flags', 'premium_type', 'public_flags'])->mapWithKeys(fn (string $field) => [$field => [$field]])->all();
    }
}
