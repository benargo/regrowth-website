<?php

namespace Tests\Support;

trait EventWebhookBody
{
    /** @return array<string, mixed> */
    protected array $eventBody = [
        'id' => '111222333444555001',
        'channelId' => '100000000000000001',
        'leaderId' => '200000000000000001',
        'leaderName' => 'Raid Leader',
        'title' => 'Weekly Raid',
        'description' => 'A raid event',
        'startTime' => 1700000000,
        'endTime' => 1700007200,
        'closingTime' => 1700000000,
        'lastUpdated' => 1699999000,
        'color' => '0,0,0',
    ];
}
