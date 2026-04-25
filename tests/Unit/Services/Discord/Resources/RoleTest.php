<?php

namespace Tests\Unit\Services\Discord\Resources;

use App\Services\Discord\Resources\Role;
use App\Services\Discord\Resources\RoleColors;
use App\Services\Discord\Resources\RoleTags;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

class RoleTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // RoleColors
    // ---------------------------------------------------------------------------

    #[Test]
    public function role_colors_constructs_with_only_primary_color(): void
    {
        $colors = RoleColors::from(['primary_color' => 16711680]);

        $this->assertSame(16711680, $colors->primary_color);
        $this->assertNull($colors->secondary_color);
        $this->assertNull($colors->tertiary_color);
    }

    #[Test]
    public function role_colors_constructs_with_gradient_colors(): void
    {
        $colors = RoleColors::from([
            'primary_color' => 16711680,
            'secondary_color' => 65280,
        ]);

        $this->assertSame(16711680, $colors->primary_color);
        $this->assertSame(65280, $colors->secondary_color);
        $this->assertNull($colors->tertiary_color);
    }

    #[Test]
    public function role_colors_constructs_with_holographic_colors(): void
    {
        $colors = RoleColors::from([
            'primary_color' => 16711680,
            'secondary_color' => 65280,
            'tertiary_color' => 255,
        ]);

        $this->assertSame(16711680, $colors->primary_color);
        $this->assertSame(65280, $colors->secondary_color);
        $this->assertSame(255, $colors->tertiary_color);
    }

    #[Test]
    public function role_colors_properties_are_readonly(): void
    {
        $colors = RoleColors::from(['primary_color' => 0]);
        $reflection = new ReflectionClass($colors);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== RoleColors::class) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly."
            );
        }
    }

    // ---------------------------------------------------------------------------
    // RoleTags
    // ---------------------------------------------------------------------------

    #[Test]
    public function role_tags_constructs_with_all_defaults(): void
    {
        $tags = RoleTags::from([]);

        $this->assertNull($tags->bot_id);
        $this->assertNull($tags->integration_id);
        $this->assertFalse($tags->premium_subscriber);
        $this->assertNull($tags->subscription_listing_id);
        $this->assertFalse($tags->available_for_purchase);
        $this->assertFalse($tags->guild_connections);
    }

    #[Test]
    public function role_tags_constructs_for_a_bot_role(): void
    {
        $tags = RoleTags::from(['bot_id' => '123456789']);

        $this->assertSame('123456789', $tags->bot_id);
        $this->assertNull($tags->integration_id);
    }

    #[Test]
    public function role_tags_constructs_for_an_integration_role(): void
    {
        $tags = RoleTags::from(['integration_id' => '987654321']);

        $this->assertNull($tags->bot_id);
        $this->assertSame('987654321', $tags->integration_id);
    }

    #[Test]
    public function role_tags_constructs_for_a_booster_role(): void
    {
        $tags = RoleTags::from(['premium_subscriber' => true]);

        $this->assertTrue($tags->premium_subscriber);
    }

    #[Test]
    public function role_tags_constructs_for_a_purchasable_role(): void
    {
        $tags = RoleTags::from([
            'subscription_listing_id' => '111222333',
            'available_for_purchase' => true,
        ]);

        $this->assertSame('111222333', $tags->subscription_listing_id);
        $this->assertTrue($tags->available_for_purchase);
    }

    #[Test]
    public function role_tags_constructs_for_a_linked_role(): void
    {
        $tags = RoleTags::from(['guild_connections' => true]);

        $this->assertTrue($tags->guild_connections);
    }

    #[Test]
    public function role_tags_properties_are_readonly(): void
    {
        $tags = RoleTags::from([]);
        $reflection = new ReflectionClass($tags);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== RoleTags::class) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly."
            );
        }
    }

    // ---------------------------------------------------------------------------
    // Role
    // ---------------------------------------------------------------------------

    #[Test]
    public function role_constructs_from_minimal_required_fields(): void
    {
        $role = Role::from([
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
        $this->assertInstanceOf(RoleColors::class, $role->colors);
        $this->assertSame(0, $role->colors->primary_color);
        $this->assertFalse($role->hoist);
        $this->assertSame(0, $role->position);
        $this->assertSame('104324160', $role->permissions);
        $this->assertFalse($role->managed);
        $this->assertFalse($role->mentionable);
        $this->assertSame(0, $role->flags);
        $this->assertNull($role->icon);
        $this->assertNull($role->unicode_emoji);
        $this->assertNull($role->tags);
    }

    #[Test]
    public function role_constructs_with_all_optional_fields(): void
    {
        $role = Role::from([
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
        $this->assertSame(16711680, $role->colors->primary_color);
        $this->assertSame(65280, $role->colors->secondary_color);
        $this->assertTrue($role->hoist);
        $this->assertSame('abc123hash', $role->icon);
        $this->assertNull($role->unicode_emoji);
        $this->assertSame(3, $role->position);
        $this->assertSame('8', $role->permissions);
        $this->assertFalse($role->managed);
        $this->assertTrue($role->mentionable);
        $this->assertInstanceOf(RoleTags::class, $role->tags);
        $this->assertSame('999', $role->tags->bot_id);
        $this->assertSame(1, $role->flags);
    }

    #[Test]
    public function role_constructs_with_unicode_emoji(): void
    {
        $role = Role::from([
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

        $this->assertSame('🔥', $role->unicode_emoji);
        $this->assertNull($role->icon);
    }

    #[Test]
    public function role_constructs_with_nested_tags(): void
    {
        $role = Role::from([
            'id' => '41771983423143937',
            'name' => 'Booster',
            'colors' => ['primary_color' => 16007990],
            'hoist' => true,
            'position' => 2,
            'permissions' => '0',
            'managed' => true,
            'mentionable' => false,
            'flags' => 0,
            'tags' => ['premium_subscriber' => true],
        ]);

        $this->assertTrue($role->managed);
        $this->assertInstanceOf(RoleTags::class, $role->tags);
        $this->assertTrue($role->tags->premium_subscriber);
    }

    #[Test]
    public function role_properties_are_readonly(): void
    {
        $role = Role::from([
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
            if ($property->getDeclaringClass()->getName() !== Role::class) {
                continue;
            }

            $this->assertTrue(
                $property->isReadOnly(),
                "Property \${$property->getName()} should be readonly."
            );
        }
    }
}
