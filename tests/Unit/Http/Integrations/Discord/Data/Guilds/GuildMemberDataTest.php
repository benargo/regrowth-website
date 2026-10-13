<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
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
class GuildMemberDataTest extends TestCase
{
    #[Test]
    public function guild_member_can_be_constructed_directly(): void
    {
        $member = new GuildMemberData(
            user: Optional::create(),
            nick: Optional::create(),
            avatar: Optional::create(),
            banner: Optional::create(),
            roles: [],
            joinedAt: Optional::create(),
            premiumSince: Optional::create(),
            deaf: false,
            mute: false,
            flags: 0,
            pending: Optional::create(),
            permissions: Optional::create(),
            communicationDisabledUntil: Optional::create(),
            avatarDecorationData: Optional::create(),
            collectibles: Optional::create(),
        );

        $this->assertInstanceOf(Optional::class, $member->user);
        $this->assertInstanceOf(Optional::class, $member->nick);
        $this->assertInstanceOf(Optional::class, $member->avatar);
        $this->assertInstanceOf(Optional::class, $member->banner);
        $this->assertSame([], $member->roles);
        $this->assertInstanceOf(Optional::class, $member->joinedAt);
        $this->assertInstanceOf(Optional::class, $member->premiumSince);
        $this->assertFalse($member->deaf);
        $this->assertFalse($member->mute);
        $this->assertSame(0, $member->flags);
        $this->assertInstanceOf(Optional::class, $member->pending);
        $this->assertInstanceOf(Optional::class, $member->permissions);
        $this->assertInstanceOf(Optional::class, $member->communicationDisabledUntil);
        $this->assertInstanceOf(Optional::class, $member->avatarDecorationData);
        $this->assertInstanceOf(Optional::class, $member->collectibles);
    }

    #[Test]
    public function it_constructs_from_empty_payload(): void
    {
        $member = GuildMemberData::from(['roles' => [], 'deaf' => false, 'mute' => false, 'flags' => 0]);

        $this->assertInstanceOf(Optional::class, $member->user);
        $this->assertInstanceOf(Optional::class, $member->nick);
        $this->assertInstanceOf(Optional::class, $member->avatar);
        $this->assertInstanceOf(Optional::class, $member->banner);
        $this->assertSame([], $member->roles);
        $this->assertInstanceOf(Optional::class, $member->joinedAt);
        $this->assertInstanceOf(Optional::class, $member->premiumSince);
        $this->assertFalse($member->deaf);
        $this->assertFalse($member->mute);
        $this->assertSame(0, $member->flags);
        $this->assertInstanceOf(Optional::class, $member->pending);
        $this->assertInstanceOf(Optional::class, $member->permissions);
        $this->assertInstanceOf(Optional::class, $member->communicationDisabledUntil);
        $this->assertInstanceOf(Optional::class, $member->avatarDecorationData);
        $this->assertInstanceOf(Optional::class, $member->collectibles);
    }

    #[Test]
    public function it_constructs_from_the_discord_example_payload(): void
    {
        $member = GuildMemberData::from([
            'user' => ['id' => '1', 'username' => 'test', 'discriminator' => '0000', 'flags' => 0, 'public_flags' => 0],
            'nick' => 'NOT API SUPPORT',
            'avatar' => null,
            'banner' => null,
            'roles' => [],
            'joined_at' => '2015-04-26T06:26:56.936000+00:00',
            'deaf' => false,
            'mute' => false,
            'flags' => 0,
        ]);

        $this->assertInstanceOf(UserData::class, $member->user);
        $this->assertSame('NOT API SUPPORT', $member->nick);
        $this->assertNull($member->avatar);
        $this->assertNull($member->banner);
        $this->assertSame([], $member->roles);
        $this->assertSame('2015-04-26T06:26:56.936000+00:00', $member->joinedAt);
        $this->assertFalse($member->deaf);
        $this->assertFalse($member->mute);
    }

    #[Test]
    public function it_stores_all_scalar_fields(): void
    {
        $member = GuildMemberData::from([
            'user' => ['id' => '123', 'username' => 'Thrall', 'discriminator' => '0001', 'flags' => 0, 'public_flags' => 0],
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

        $this->assertInstanceOf(UserData::class, $member->user);
        $this->assertSame('123', $member->user->id);
        $this->assertSame('Thrall', $member->user->username);
        $this->assertSame('Warchief', $member->nick);
        $this->assertSame('avatar_hash', $member->avatar);
        $this->assertSame('banner_hash', $member->banner);
        $this->assertSame(['111', '222'], $member->roles);
        $this->assertSame('2024-01-01T00:00:00Z', $member->joinedAt);
        $this->assertSame('2024-06-01T00:00:00Z', $member->premiumSince);
        $this->assertTrue($member->deaf);
        $this->assertTrue($member->mute);
        $this->assertSame(2, $member->flags);
        $this->assertFalse($member->pending);
        $this->assertSame('8', $member->permissions);
        $this->assertSame('2024-12-31T23:59:59Z', $member->communicationDisabledUntil);
    }

    #[Test]
    public function it_stores_avatar_decoration_data_and_collectibles(): void
    {
        $decoration = ['asset' => 'decoration_hash', 'sku_id' => '999'];
        $collectibles = ['nameplate' => ['asset' => 'nameplate_hash', 'label' => 'Gold']];

        $member = GuildMemberData::from([
            'roles' => [],
            'deaf' => false,
            'mute' => false,
            'flags' => 0,
            'avatar_decoration_data' => $decoration,
            'collectibles' => $collectibles,
        ]);

        $this->assertSame($decoration, $member->avatarDecorationData);
        $this->assertSame($collectibles, $member->collectibles);
    }

    #[Test]
    public function it_accepts_null_for_avatar_decoration_data_and_collectibles(): void
    {
        $member = GuildMemberData::from([
            ...$this->minimalPayload(),
            'avatar_decoration_data' => null,
            'collectibles' => null,
        ]);

        $this->assertNull($member->avatarDecorationData);
        $this->assertNull($member->collectibles);
    }

    #[Test]
    public function it_stores_null_for_nullable_optional_fields(): void
    {
        $member = GuildMemberData::from([...$this->minimalPayload(), 'joined_at' => null]);
        $this->assertNull($member->joinedAt);

        $member = GuildMemberData::from([...$this->minimalPayload(), 'premium_since' => null]);
        $this->assertNull($member->premiumSince);

        $member = GuildMemberData::from([...$this->minimalPayload(), 'communication_disabled_until' => null]);
        $this->assertNull($member->communicationDisabledUntil);
    }

    #[Test]
    public function all_properties_are_readonly(): void
    {
        $member = GuildMemberData::from(['roles' => [], 'deaf' => false, 'mute' => false, 'flags' => 0]);
        $reflection = new ReflectionClass($member);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== GuildMemberData::class) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly."
            );
        }
    }

    #[Test]
    public function it_maps_snake_case_keys_to_camel_case_properties_and_back(): void
    {
        $member = GuildMemberData::from([
            'user' => ['id' => '111111111111111111', 'username' => 'thrall', 'discriminator' => '0', 'flags' => 0, 'public_flags' => 0],
            'roles' => ['222222222222222222'],
            'joined_at' => '2026-01-01T00:00:00+00:00',
            'communication_disabled_until' => null,
            'deaf' => false,
            'mute' => false,
            'flags' => 0,
        ]);

        $this->assertInstanceOf(UserData::class, $member->user);
        $this->assertSame('2026-01-01T00:00:00+00:00', $member->joinedAt);
        $this->assertNull($member->communicationDisabledUntil);

        $array = $member->toArray();

        $this->assertSame('2026-01-01T00:00:00+00:00', $array['joined_at']);
        $this->assertSame(0, $array['user']['public_flags']);
        $this->assertArrayNotHasKey('joinedAt', $array);
    }

    /** @return array<string, mixed> */
    private function minimalPayload(): array
    {
        return ['roles' => [], 'deaf' => false, 'mute' => false, 'flags' => 0];
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('nonNullableOptionalFields')]
    public function it_rejects_null_for_fields_that_can_only_be_absent(string $field): void
    {
        $this->expectException(ValidationException::class);

        GuildMemberData::validate([...$this->minimalPayload(), 'roles' => ['1'], $field => null]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonNullableOptionalFields(): array
    {
        return collect(['user', 'pending', 'permissions'])->mapWithKeys(fn (string $field) => [$field => [$field]])->all();
    }
}
