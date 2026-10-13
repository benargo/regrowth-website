<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\RoleColorsData;
use App\Http\Integrations\Discord\Data\Guilds\RoleData;
use App\Http\Integrations\Discord\Data\Guilds\RoleTagsData;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('discord-integration')]
class RoleDataTest extends TestCase
{
    #[Test]
    public function role_colors_can_be_constructed_directly(): void
    {
        $colors = new RoleColorsData(
            primaryColor: 16711680,
            secondaryColor: Optional::create(),
            tertiaryColor: Optional::create(),
        );

        $this->assertSame(16711680, $colors->primaryColor);
        $this->assertInstanceOf(Optional::class, $colors->secondaryColor);
        $this->assertInstanceOf(Optional::class, $colors->tertiaryColor);
    }

    #[Test]
    public function role_colors_constructs_with_only_primary_color(): void
    {
        $colors = RoleColorsData::from(['primary_color' => 16711680]);

        $this->assertSame(16711680, $colors->primaryColor);
        $this->assertInstanceOf(Optional::class, $colors->secondaryColor);
        $this->assertInstanceOf(Optional::class, $colors->tertiaryColor);
    }

    #[Test]
    public function role_colors_constructs_with_gradient_colors(): void
    {
        $colors = RoleColorsData::from([
            'primary_color' => 16711680,
            'secondary_color' => 65280,
        ]);

        $this->assertSame(16711680, $colors->primaryColor);
        $this->assertSame(65280, $colors->secondaryColor);
        $this->assertInstanceOf(Optional::class, $colors->tertiaryColor);
    }

    #[Test]
    public function role_colors_constructs_with_holographic_colors(): void
    {
        $colors = RoleColorsData::from([
            'primary_color' => 16711680,
            'secondary_color' => 65280,
            'tertiary_color' => 255,
        ]);

        $this->assertSame(16711680, $colors->primaryColor);
        $this->assertSame(65280, $colors->secondaryColor);
        $this->assertSame(255, $colors->tertiaryColor);
    }

    #[Test]
    public function role_colors_properties_are_readonly(): void
    {
        $colors = RoleColorsData::from(['primary_color' => 0]);
        $reflection = new ReflectionClass($colors);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== RoleColorsData::class) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly."
            );
        }
    }

    // ==================== roleTags ====================

    #[Test]
    public function role_tags_can_be_constructed_directly(): void
    {
        $tags = new RoleTagsData(
            botId: Optional::create(),
            integrationId: Optional::create(),
            premiumSubscriber: Optional::create(),
            subscriptionListingId: Optional::create(),
            availableForPurchase: Optional::create(),
            guildConnections: Optional::create(),
        );

        $this->assertInstanceOf(Optional::class, $tags->botId);
        $this->assertInstanceOf(Optional::class, $tags->integrationId);
        $this->assertInstanceOf(Optional::class, $tags->premiumSubscriber);
        $this->assertInstanceOf(Optional::class, $tags->subscriptionListingId);
        $this->assertInstanceOf(Optional::class, $tags->availableForPurchase);
        $this->assertInstanceOf(Optional::class, $tags->guildConnections);
    }

    #[Test]
    public function role_tags_constructs_with_all_defaults(): void
    {
        $tags = RoleTagsData::from([]);

        $this->assertInstanceOf(Optional::class, $tags->botId);
        $this->assertInstanceOf(Optional::class, $tags->integrationId);
        $this->assertInstanceOf(Optional::class, $tags->premiumSubscriber);
        $this->assertInstanceOf(Optional::class, $tags->subscriptionListingId);
        $this->assertInstanceOf(Optional::class, $tags->availableForPurchase);
        $this->assertInstanceOf(Optional::class, $tags->guildConnections);
    }

    #[Test]
    public function role_tags_constructs_for_a_bot_role(): void
    {
        $tags = RoleTagsData::from(['bot_id' => '123456789']);

        $this->assertSame('123456789', $tags->botId);
        $this->assertInstanceOf(Optional::class, $tags->integrationId);
    }

    #[Test]
    public function role_tags_constructs_for_an_integration_role(): void
    {
        $tags = RoleTagsData::from(['integration_id' => '987654321']);

        $this->assertInstanceOf(Optional::class, $tags->botId);
        $this->assertSame('987654321', $tags->integrationId);
    }

    #[Test]
    public function role_tags_constructs_for_a_booster_role(): void
    {
        $tags = RoleTagsData::from(['premium_subscriber' => null]);

        $this->assertNull($tags->premiumSubscriber);
    }

    #[Test]
    public function role_tags_constructs_for_a_purchasable_role(): void
    {
        $tags = RoleTagsData::from([
            'subscription_listing_id' => '111222333',
            'available_for_purchase' => null,
        ]);

        $this->assertSame('111222333', $tags->subscriptionListingId);
        $this->assertNull($tags->availableForPurchase);
    }

    #[Test]
    public function role_tags_constructs_for_a_linked_role(): void
    {
        $tags = RoleTagsData::from(['guild_connections' => null]);

        $this->assertNull($tags->guildConnections);
    }

    #[Test]
    public function role_tags_properties_are_readonly(): void
    {
        $tags = RoleTagsData::from([]);
        $reflection = new ReflectionClass($tags);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== RoleTagsData::class) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly."
            );
        }
    }

    // ==================== role construction ====================

    #[Test]
    public function role_can_be_constructed_directly(): void
    {
        $colors = RoleColorsData::from(['primary_color' => 0]);

        $role = new RoleData(
            id: '41771983423143937',
            name: 'WE DEM BOYZZ!!!!!!',
            colors: $colors,
            hoist: false,
            position: 0,
            permissions: '104324160',
            managed: false,
            mentionable: false,
            flags: 0,
            icon: Optional::create(),
            unicodeEmoji: Optional::create(),
            tags: Optional::create(),
        );

        $this->assertSame('41771983423143937', $role->id);
        $this->assertSame('WE DEM BOYZZ!!!!!!', $role->name);
        $this->assertSame($colors, $role->colors);
        $this->assertFalse($role->hoist);
        $this->assertSame(0, $role->position);
        $this->assertSame('104324160', $role->permissions);
        $this->assertFalse($role->managed);
        $this->assertFalse($role->mentionable);
        $this->assertSame(0, $role->flags);
        $this->assertInstanceOf(Optional::class, $role->icon);
        $this->assertInstanceOf(Optional::class, $role->unicodeEmoji);
        $this->assertInstanceOf(Optional::class, $role->tags);
    }

    #[Test]
    public function role_constructs_from_minimal_required_fields(): void
    {
        $role = RoleData::from([
            'id' => '41771983423143937',
            'name' => 'WE DEM BOYZZ!!!!!!',
            'colors' => ['primary_color' => 0],
            'hoist' => false,
            'position' => 0,
            'permissions' => '104324160',
            'managed' => false,
            'mentionable' => false,
            'flags' => 0,
        ]);

        $this->assertSame('41771983423143937', $role->id);
        $this->assertSame('WE DEM BOYZZ!!!!!!', $role->name);
        $this->assertInstanceOf(RoleColorsData::class, $role->colors);
        $this->assertSame(0, $role->colors->primaryColor);
        $this->assertFalse($role->hoist);
        $this->assertSame(0, $role->position);
        $this->assertSame('104324160', $role->permissions);
        $this->assertFalse($role->managed);
        $this->assertFalse($role->mentionable);
        $this->assertSame(0, $role->flags);
        $this->assertInstanceOf(Optional::class, $role->icon);
        $this->assertInstanceOf(Optional::class, $role->unicodeEmoji);
        $this->assertInstanceOf(Optional::class, $role->tags);
    }

    #[Test]
    public function role_constructs_with_all_optional_fields(): void
    {
        $role = RoleData::from([
            'id' => '41771983423143937',
            'name' => 'Admins',
            'colors' => ['primary_color' => 16711680, 'secondary_color' => 65280],
            'hoist' => true,
            'icon' => 'abc123hash',
            'unicode_emoji' => null,
            'position' => 3,
            'permissions' => '8',
            'managed' => false,
            'mentionable' => true,
            'tags' => ['bot_id' => '999'],
            'flags' => 1,
        ]);

        $this->assertSame('41771983423143937', $role->id);
        $this->assertSame('Admins', $role->name);
        $this->assertSame(16711680, $role->colors->primaryColor);
        $this->assertSame(65280, $role->colors->secondaryColor);
        $this->assertTrue($role->hoist);
        $this->assertSame('abc123hash', $role->icon);
        $this->assertNull($role->unicodeEmoji);
        $this->assertSame(3, $role->position);
        $this->assertSame('8', $role->permissions);
        $this->assertFalse($role->managed);
        $this->assertTrue($role->mentionable);
        $this->assertInstanceOf(RoleTagsData::class, $role->tags);
        $this->assertSame('999', $role->tags->botId);
        $this->assertSame(1, $role->flags);
    }

    #[Test]
    public function it_stores_null_for_nullable_optional_fields(): void
    {
        $role = RoleData::from([
            'id' => '41771983423143937',
            'name' => 'WE DEM BOYZZ!!!!!!',
            'colors' => ['primary_color' => 0],
            'hoist' => false,
            'position' => 0,
            'permissions' => '104324160',
            'managed' => false,
            'mentionable' => false,
            'flags' => 0,
            'icon' => null,
        ]);

        $this->assertNull($role->icon);
    }

    #[Test]
    public function role_constructs_with_unicode_emoji(): void
    {
        $role = RoleData::from([
            'id' => '41771983423143937',
            'name' => 'Emoji Role',
            'colors' => ['primary_color' => 0],
            'hoist' => false,
            'unicode_emoji' => '🔥',
            'position' => 1,
            'permissions' => '0',
            'managed' => false,
            'mentionable' => false,
            'flags' => 0,
        ]);

        $this->assertSame('🔥', $role->unicodeEmoji);
        $this->assertInstanceOf(Optional::class, $role->icon);
    }

    #[Test]
    public function role_constructs_with_nested_tags(): void
    {
        $role = RoleData::from([
            'id' => '41771983423143937',
            'name' => 'Booster',
            'colors' => ['primary_color' => 16007990],
            'hoist' => true,
            'position' => 2,
            'permissions' => '0',
            'managed' => true,
            'mentionable' => false,
            'flags' => 0,
            'tags' => ['premium_subscriber' => null],
        ]);

        $this->assertTrue($role->managed);
        $this->assertInstanceOf(RoleTagsData::class, $role->tags);
        $this->assertNull($role->tags->premiumSubscriber);
    }

    #[Test]
    public function role_properties_are_readonly(): void
    {
        $role = RoleData::from([
            'id' => '1',
            'name' => 'Test',
            'colors' => ['primary_color' => 0],
            'hoist' => false,
            'position' => 0,
            'permissions' => '0',
            'managed' => false,
            'mentionable' => false,
            'flags' => 0,
        ]);
        $reflection = new ReflectionClass($role);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== RoleData::class) {
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
        $role = RoleData::from([
            'id' => '111111111111111111',
            'name' => 'Officer',
            'colors' => ['primary_color' => 3447003, 'secondary_color' => null],
            'hoist' => true,
            'position' => 10,
            'permissions' => '0',
            'managed' => false,
            'mentionable' => true,
            'flags' => 0,
            'unicode_emoji' => '🛡️',
            'tags' => ['bot_id' => '333333333333333333', 'premium_subscriber' => null],
        ]);

        $this->assertSame('🛡️', $role->unicodeEmoji);
        $this->assertSame(3447003, $role->colors->primaryColor);
        $this->assertSame('333333333333333333', $role->tags->botId);

        $array = $role->toArray();

        $this->assertSame('🛡️', $array['unicode_emoji']);
        $this->assertSame(3447003, $array['colors']['primary_color']);
        $this->assertSame('333333333333333333', $array['tags']['bot_id']);
        $this->assertArrayNotHasKey('unicodeEmoji', $array);
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('nonNullableOptionalFields')]
    public function it_rejects_null_for_fields_that_can_only_be_absent(string $field): void
    {
        $this->expectException(ValidationException::class);

        RoleTagsData::validate([$field => null]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonNullableOptionalFields(): array
    {
        return collect(['bot_id', 'integration_id', 'subscription_listing_id'])->mapWithKeys(fn (string $field) => [$field => [$field]])->all();
    }
}
