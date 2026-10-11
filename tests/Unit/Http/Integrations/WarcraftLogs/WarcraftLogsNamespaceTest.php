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
        $this->assertSame('anniversary', WarcraftLogsNamespace::Anniversary->value);
        $this->assertSame('classic', WarcraftLogsNamespace::Classic->value);
        $this->assertSame('era', WarcraftLogsNamespace::Era->value);
        $this->assertSame('forever', WarcraftLogsNamespace::Forever->value);
        $this->assertSame('retail', WarcraftLogsNamespace::Retail->value);
        $this->assertSame('season_of_discovery', WarcraftLogsNamespace::SeasonOfDiscovery->value);
    }

    // ==================== baseUrl ====================

    #[Test]
    #[Group('happy-path')]
    public function base_url_resolves_the_correct_host_for_each_case(): void
    {
        $this->assertSame(
            'https://fresh.warcraftlogs.com/api/v2/client',
            WarcraftLogsNamespace::Anniversary->baseUrl(),
        );
        $this->assertSame(
            'https://classic.warcraftlogs.com/api/v2/client',
            WarcraftLogsNamespace::Classic->baseUrl(),
        );
        $this->assertSame(
            'https://vanilla.warcraftlogs.com/api/v2/client',
            WarcraftLogsNamespace::Era->baseUrl(),
        );
        $this->assertSame(
            'https://forever.warcraftlogs.com/api/v2/client',
            WarcraftLogsNamespace::Forever->baseUrl(),
        );
        $this->assertSame(
            'https://www.warcraftlogs.com/api/v2/client',
            WarcraftLogsNamespace::Retail->baseUrl(),
        );
        $this->assertSame(
            'https://sod.warcraftlogs.com/api/v2/client',
            WarcraftLogsNamespace::SeasonOfDiscovery->baseUrl(),
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
            WarcraftLogsNamespace::Anniversary->label(),
        );
        $this->assertSame(
            'Mists of Pandaria Classic',
            WarcraftLogsNamespace::Classic->label(),
        );
        $this->assertSame(
            'Classic Era',
            WarcraftLogsNamespace::Era->label(),
        );
        $this->assertSame(
            'World of Warcraft: Forever',
            WarcraftLogsNamespace::Forever->label(),
        );
        $this->assertSame(
            'World of Warcraft',
            WarcraftLogsNamespace::Retail->label(),
        );
        $this->assertSame(
            'Season of Discovery',
            WarcraftLogsNamespace::SeasonOfDiscovery->label(),
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
