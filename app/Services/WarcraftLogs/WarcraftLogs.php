<?php

namespace App\Services\WarcraftLogs;

class WarcraftLogs
{
    /**
     * Initialize the WarcraftLogs service with configuration and authentication.
     */
    public function __construct(
        protected array $config,
        protected AuthenticationHandler $auth
    ) {}

    public function guild(Guild $guild): Guild
    {
        return $guild;
    }

    public function guildTags(GuildTags $guildTags): GuildTags
    {
        return $guildTags;
    }

    public function attendance(Attendance $attendance): Attendance
    {
        return $attendance;
    }

    public function reports(Reports $reports): Reports
    {
        return $reports;
    }
}
