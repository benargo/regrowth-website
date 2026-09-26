<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class WarcraftLogsNamespaceTest extends TestCase
{
    // ==================== cases ====================

    #[Test]
    #[Group('happy-path')]
    public function it_has_exactly_six_cases(): void
    {
        $this->assertCount(6, WarcraftLogsNamespace::cases());
    }

    #[Test]
    #[Group('happy-path')]
    public function each_case_is_backed_by_its_snake_case_name(): void
    {
        $this->assertSame('anniversary', WarcraftLogsNamespace::ANNIVERSARY->value);
        $this->assertSame('classic', WarcraftLogsNamespace::CLASSIC->value);
        $this->assertSame('era', WarcraftLogsNamespace::ERA->value);
        $this->assertSame('forever', WarcraftLogsNamespace::FOREVER->value);
        $this->assertSame('retail', WarcraftLogsNamespace::RETAIL->value);
        $this->assertSame('season_of_discovery', WarcraftLogsNamespace::SEASON_OF_DISCOVERY->value);
    }

    // ==================== baseUrl ====================

    #[Test]
    #[Group('happy-path')]
    public function base_url_resolves_the_correct_host_for_each_case(): void
    {
        $this->assertSame(
            'https://fresh.warcraftlogs.com/api/v2/client',
            WarcraftLogsNamespace::ANNIVERSARY->baseUrl(),
        );
        $this->assertSame(
            'https://classic.warcraftlogs.com/api/v2/client',
            WarcraftLogsNamespace::CLASSIC->baseUrl(),
        );
        $this->assertSame(
            'https://vanilla.warcraftlogs.com/api/v2/client',
            WarcraftLogsNamespace::ERA->baseUrl(),
        );
        $this->assertSame(
            'https://forever.warcraftlogs.com/api/v2/client',
            WarcraftLogsNamespace::FOREVER->baseUrl(),
        );
        $this->assertSame(
            'https://www.warcraftlogs.com/api/v2/client',
            WarcraftLogsNamespace::RETAIL->baseUrl(),
        );
        $this->assertSame(
            'https://sod.warcraftlogs.com/api/v2/client',
            WarcraftLogsNamespace::SEASON_OF_DISCOVERY->baseUrl(),
        );
    }

    #[Test]
    #[Group('happy-path')]
    public function base_url_is_unique_per_case(): void
    {
        $urls = array_map(
            fn (WarcraftLogsNamespace $namespace): string => $namespace->baseUrl(),
            WarcraftLogsNamespace::cases(),
        );

        $this->assertCount(count($urls), array_unique($urls));
    }

    // ==================== label ====================

    #[Test]
    #[Group('happy-path')]
    public function label_resolves_the_correct_display_name_for_each_case(): void
    {
        $this->assertSame(
            'The Burning Crusade Classic Anniversary',
            WarcraftLogsNamespace::ANNIVERSARY->label(),
        );
        $this->assertSame(
            'Mists of Pandaria Classic',
            WarcraftLogsNamespace::CLASSIC->label(),
        );
        $this->assertSame(
            'Classic Era',
            WarcraftLogsNamespace::ERA->label(),
        );
        $this->assertSame(
            'World of Warcraft: Forever',
            WarcraftLogsNamespace::FOREVER->label(),
        );
        $this->assertSame(
            'World of Warcraft',
            WarcraftLogsNamespace::RETAIL->label(),
        );
        $this->assertSame(
            'Season of Discovery',
            WarcraftLogsNamespace::SEASON_OF_DISCOVERY->label(),
        );
    }

    #[Test]
    #[Group('happy-path')]
    public function label_is_unique_per_case(): void
    {
        $labels = array_map(
            fn (WarcraftLogsNamespace $namespace): string => $namespace->label(),
            WarcraftLogsNamespace::cases(),
        );

        $this->assertCount(count($labels), array_unique($labels));
    }
}
