<?php

namespace Tests\Unit\Data\Navigation;

use App\Data\Navigation\LatestDiscussionData;
use App\Data\Navigation\NavigationData;
use App\Data\Navigation\NextRaidData;
use App\Data\Navigation\SignupLineData;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class NavigationDataTest extends TestCase
{
    // ==================== toArray ====================

    #[Test]
    #[Group('contract')]
    public function to_array_emits_the_navigation_shape_with_snake_case_keys(): void
    {
        $navigation = new NavigationData(
            nextRaid: $this->nextRaid(),
            latestDiscussions: [$this->latestDiscussion()],
            discordInviteUrl: 'https://discord.gg/regrowth',
            warcraftLogsUrl: 'https://www.warcraftlogs.com/guild/id/1',
        );

        $this->assertSame([
            'next_raid' => [
                'id' => 'event-1',
                'title' => 'Karazhan',
                'start_time' => '2025-01-01T19:00:00+01:00',
                'plan_url' => 'https://example.test/raiding/plans/event-1',
                'signup' => [
                    'label' => "You're signed up.",
                    'icon' => 'fa-check',
                ],
            ],
            'latest_discussions' => [
                [
                    'id' => 7,
                    'item_name' => 'Gloves of the Searing Grip',
                    'item_quality_class' => 'text-epic',
                    'item_icon' => 'https://example.test/icons/gloves.jpg',
                    'url' => 'https://example.test/loot/items/1',
                    'boss_name' => 'Gruul the Dragonkiller',
                    'posted_ago' => '2 hours ago',
                    'replies_count' => 3,
                ],
            ],
            'discord_invite_url' => 'https://discord.gg/regrowth',
            'warcraft_logs_url' => 'https://www.warcraftlogs.com/guild/id/1',
        ], $navigation->toArray());
    }

    #[Test]
    #[Group('contract')]
    public function to_array_keeps_null_and_empty_values(): void
    {
        $navigation = new NavigationData(
            nextRaid: null,
            latestDiscussions: [],
            discordInviteUrl: null,
            warcraftLogsUrl: null,
        );

        $this->assertSame([
            'next_raid' => null,
            'latest_discussions' => [],
            'discord_invite_url' => null,
            'warcraft_logs_url' => null,
        ], $navigation->toArray());
    }

    // ==================== from ====================

    #[Test]
    public function from_hydrates_latest_discussions_into_data_objects(): void
    {
        $navigation = NavigationData::from([
            'next_raid' => null,
            'latest_discussions' => [$this->latestDiscussion()->toArray()],
            'discord_invite_url' => null,
            'warcraft_logs_url' => null,
        ]);

        $this->assertCount(1, $navigation->latestDiscussions);
        $this->assertInstanceOf(LatestDiscussionData::class, $navigation->latestDiscussions[0]);
        $this->assertSame('Gloves of the Searing Grip', $navigation->latestDiscussions[0]->itemName);
    }

    // ==================== helpers ====================

    private function nextRaid(): NextRaidData
    {
        return new NextRaidData(
            id: 'event-1',
            title: 'Karazhan',
            startTime: CarbonImmutable::parse('2025-01-01 19:00', 'Europe/Paris'),
            planUrl: 'https://example.test/raiding/plans/event-1',
            signup: new SignupLineData("You're signed up.", 'fa-check'),
        );
    }

    private function latestDiscussion(): LatestDiscussionData
    {
        return new LatestDiscussionData(
            id: 7,
            itemName: 'Gloves of the Searing Grip',
            itemQualityClass: 'text-epic',
            itemIcon: 'https://example.test/icons/gloves.jpg',
            url: 'https://example.test/loot/items/1',
            bossName: 'Gruul the Dragonkiller',
            postedAgo: '2 hours ago',
            repliesCount: 3,
        );
    }
}
