<?php

namespace Tests\Unit\Http\Integrations\Blizzard;

use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Region;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
class BlizzardNamespaceTest extends TestCase
{
    // ==================== cases ====================

    #[Test]
    #[Group('happy-path')]
    public function it_has_exactly_five_cases(): void
    {
        $this->assertCount(5, BlizzardNamespace::cases());
    }

    #[Test]
    #[Group('happy-path')]
    public function each_case_is_backed_by_its_own_name(): void
    {
        $this->assertSame('anniversary', BlizzardNamespace::ANNIVERSARY->value);
        $this->assertSame('classic', BlizzardNamespace::CLASSIC->value);
        $this->assertSame('era', BlizzardNamespace::ERA->value);
        $this->assertSame('forever', BlizzardNamespace::FOREVER->value);
        $this->assertSame('retail', BlizzardNamespace::RETAIL->value);
    }

    // ==================== value ====================

    #[Test]
    #[Group('happy-path')]
    public function value_without_a_category_returns_the_case_value(): void
    {
        $this->assertSame('anniversary', BlizzardNamespace::ANNIVERSARY->value());
        $this->assertSame('retail', BlizzardNamespace::RETAIL->value());
    }

    #[Test]
    #[Group('happy-path')]
    public function value_builds_the_full_namespace_for_each_case(): void
    {
        $this->assertSame(
            'static-classicann-eu',
            BlizzardNamespace::ANNIVERSARY->value('static', Region::EU),
        );
        $this->assertSame(
            'static-classic-eu',
            BlizzardNamespace::CLASSIC->value('static', Region::EU),
        );
        $this->assertSame(
            'static-classic1x-eu',
            BlizzardNamespace::ERA->value('static', Region::EU),
        );
        $this->assertSame(
            'static-forever-eu',
            BlizzardNamespace::FOREVER->value('static', Region::EU),
        );
        $this->assertSame(
            'static-eu',
            BlizzardNamespace::RETAIL->value('static', Region::EU),
        );
    }

    #[Test]
    #[Group('happy-path')]
    public function value_interpolates_the_region_value_not_the_enum(): void
    {
        $this->assertSame(
            'static-classicann-us',
            BlizzardNamespace::ANNIVERSARY->value('static', Region::US),
        );
        $this->assertSame(
            'static-classicann-kr',
            BlizzardNamespace::ANNIVERSARY->value('static', Region::KR),
        );
        $this->assertSame(
            'static-classicann-tw',
            BlizzardNamespace::ANNIVERSARY->value('static', Region::TW),
        );
    }

    #[Test]
    #[Group('validation')]
    public function value_rejects_an_unknown_category(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid category: bogus');

        BlizzardNamespace::ANNIVERSARY->value('bogus', Region::EU);
    }

    #[Test]
    #[Group('happy-path')]
    public function value_falls_back_to_the_configured_region(): void
    {
        config(['services.blizzard.region' => 'us']);

        $this->assertSame(
            'static-classicann-us',
            BlizzardNamespace::ANNIVERSARY->value('static'),
        );
    }

    // ==================== category helpers ====================

    #[Test]
    #[Group('happy-path')]
    public function category_helpers_prefix_the_correct_category(): void
    {
        $this->assertSame(
            'dynamic-classicann-eu',
            BlizzardNamespace::ANNIVERSARY->forDynamicRequests(Region::EU),
        );
        $this->assertSame(
            'profile-classicann-eu',
            BlizzardNamespace::ANNIVERSARY->forProfileRequests(Region::EU),
        );
        $this->assertSame(
            'static-classicann-eu',
            BlizzardNamespace::ANNIVERSARY->forStaticRequests(Region::EU),
        );
    }

    #[Test]
    #[Group('happy-path')]
    public function category_helpers_fall_back_to_the_configured_region(): void
    {
        config(['services.blizzard.region' => 'kr']);

        $this->assertSame(
            'profile-classicann-kr',
            BlizzardNamespace::ANNIVERSARY->forProfileRequests(),
        );
    }

    // ==================== requiresRealm ====================

    #[Test]
    #[Group('happy-path')]
    public function requires_realm_is_true_for_every_case_except_forever(): void
    {
        $this->assertTrue(BlizzardNamespace::ANNIVERSARY->requiresRealm());
        $this->assertTrue(BlizzardNamespace::CLASSIC->requiresRealm());
        $this->assertTrue(BlizzardNamespace::ERA->requiresRealm());
        $this->assertTrue(BlizzardNamespace::RETAIL->requiresRealm());
    }

    #[Test]
    #[Group('happy-path')]
    public function requires_realm_is_false_for_forever(): void
    {
        $this->assertFalse(BlizzardNamespace::FOREVER->requiresRealm());
    }

    // ==================== expansionUrlSegment ====================

    #[Test]
    #[Group('happy-path')]
    public function expansion_url_segment_maps_each_case_to_its_wowhead_path(): void
    {
        $this->assertSame('tbc', BlizzardNamespace::ANNIVERSARY->expansionUrlSegment());
        $this->assertSame('tbc', BlizzardNamespace::CLASSIC->expansionUrlSegment());
        $this->assertSame('classic', BlizzardNamespace::ERA->expansionUrlSegment());
        $this->assertSame('forever', BlizzardNamespace::FOREVER->expansionUrlSegment());
        $this->assertSame('', BlizzardNamespace::RETAIL->expansionUrlSegment());
    }

    // ==================== default ====================

    #[Test]
    #[Group('happy-path')]
    public function default_resolves_the_configured_namespace(): void
    {
        config(['services.blizzard.namespace' => 'era']);

        $this->assertSame(BlizzardNamespace::ERA, BlizzardNamespace::default());
    }

    #[Test]
    #[Group('validation')]
    public function default_throws_when_unconfigured(): void
    {
        config(['services.blizzard.namespace' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Blizzard namespace: ""');

        BlizzardNamespace::default();
    }

    #[Test]
    #[Group('validation')]
    public function default_throws_for_an_unknown_configured_namespace(): void
    {
        config(['services.blizzard.namespace' => 'bogus']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Blizzard namespace: "bogus"');

        BlizzardNamespace::default();
    }
}
