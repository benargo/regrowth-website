<?php

namespace Tests\Unit\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Attendance;
use App\Services\WarcraftLogs\AuthenticationHandler;
use App\Services\WarcraftLogs\Guild;
use App\Services\WarcraftLogs\GuildTags;
use App\Services\WarcraftLogs\Reports;
use App\Services\WarcraftLogs\WarcraftLogs;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WarcraftLogsPassThroughTest extends TestCase
{
    private WarcraftLogs $service;

    protected function setUp(): void
    {
        parent::setUp();

        $auth = Mockery::mock(AuthenticationHandler::class);
        $this->service = new WarcraftLogs(['guild_id' => 774848], $auth);
    }

    #[Test]
    public function it_returns_the_guild_instance(): void
    {
        $guild = Mockery::mock(Guild::class);

        $this->assertSame($guild, $this->service->guild($guild));
    }

    #[Test]
    public function it_returns_the_guild_tags_instance(): void
    {
        $guildTags = Mockery::mock(GuildTags::class);

        $this->assertSame($guildTags, $this->service->guildTags($guildTags));
    }

    #[Test]
    public function it_returns_the_attendance_instance(): void
    {
        $attendance = Mockery::mock(Attendance::class);

        $this->assertSame($attendance, $this->service->attendance($attendance));
    }

    #[Test]
    public function it_returns_the_reports_instance(): void
    {
        $reports = Mockery::mock(Reports::class);

        $this->assertSame($reports, $this->service->reports($reports));
    }
}
