<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Guilds;

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
class RoleTagsDataTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_directly(): void
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
    public function it_constructs_with_all_defaults(): void
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
    public function it_constructs_for_a_bot_role(): void
    {
        $tags = RoleTagsData::from(['bot_id' => '123456789']);

        $this->assertSame('123456789', $tags->botId);
        $this->assertInstanceOf(Optional::class, $tags->integrationId);
    }

    #[Test]
    public function it_constructs_for_an_integration_role(): void
    {
        $tags = RoleTagsData::from(['integration_id' => '987654321']);

        $this->assertInstanceOf(Optional::class, $tags->botId);
        $this->assertSame('987654321', $tags->integrationId);
    }

    #[Test]
    public function it_constructs_for_a_booster_role(): void
    {
        $tags = RoleTagsData::from(['premium_subscriber' => null]);

        $this->assertNull($tags->premiumSubscriber);
    }

    #[Test]
    public function it_constructs_for_a_purchasable_role(): void
    {
        $tags = RoleTagsData::from([
            'subscription_listing_id' => '111222333',
            'available_for_purchase' => null,
        ]);

        $this->assertSame('111222333', $tags->subscriptionListingId);
        $this->assertNull($tags->availableForPurchase);
    }

    #[Test]
    public function it_constructs_for_a_linked_role(): void
    {
        $tags = RoleTagsData::from(['guild_connections' => null]);

        $this->assertNull($tags->guildConnections);
    }

    #[Test]
    public function its_properties_are_readonly(): void
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
