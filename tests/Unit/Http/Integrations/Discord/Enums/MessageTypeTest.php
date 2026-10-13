<?php

namespace Tests\Unit\Http\Integrations\Discord\Enums;

use App\Http\Integrations\Discord\Enums\MessageType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('discord-integration')]
class MessageTypeTest extends TestCase
{
    #[Test]
    #[DataProvider('nonDeletableTypes')]
    public function non_deletable_message_types_return_false(MessageType $type): void
    {
        $this->assertFalse($type->isDeletable());
    }

    public static function nonDeletableTypes(): array
    {
        return [
            'RecipientAdd' => [MessageType::RecipientAdd],
            'RecipientRemove' => [MessageType::RecipientRemove],
            'Call' => [MessageType::Call],
            'ChannelNameChange' => [MessageType::ChannelNameChange],
            'ChannelIconChange' => [MessageType::ChannelIconChange],
            'ThreadStarterMessage' => [MessageType::ThreadStarterMessage],
        ];
    }

    #[Test]
    #[DataProvider('deletableTypes')]
    public function deletable_message_types_return_true(MessageType $type): void
    {
        $this->assertTrue($type->isDeletable());
    }

    public static function deletableTypes(): array
    {
        return [
            'Default' => [MessageType::Default],
            'Reply' => [MessageType::Reply],
            'ChatInputCommand' => [MessageType::ChatInputCommand],
            'UserJoin' => [MessageType::UserJoin],
            'GuildBoost' => [MessageType::GuildBoost],
            'AutoModerationAction' => [MessageType::AutoModerationAction],
            'GuildIncidentAlertModeEnabled' => [MessageType::GuildIncidentAlertModeEnabled],
            'GuildIncidentAlertModeDisabled' => [MessageType::GuildIncidentAlertModeDisabled],
            'GuildIncidentReportRaid' => [MessageType::GuildIncidentReportRaid],
            'GuildIncidentReportFalseAlarm' => [MessageType::GuildIncidentReportFalseAlarm],
            'PurchaseNotification' => [MessageType::PurchaseNotification],
            'PollResult' => [MessageType::PollResult],
        ];
    }

    #[Test]
    #[DataProvider('newerWireValues')]
    public function it_resolves_newer_discord_message_types(int $value, MessageType $expected): void
    {
        $this->assertSame($expected, MessageType::from($value));
    }

    /**
     * @return array<string, array{int, MessageType}>
     */
    public static function newerWireValues(): array
    {
        return [
            'GuildIncidentAlertModeEnabled' => [36, MessageType::GuildIncidentAlertModeEnabled],
            'GuildIncidentAlertModeDisabled' => [37, MessageType::GuildIncidentAlertModeDisabled],
            'GuildIncidentReportRaid' => [38, MessageType::GuildIncidentReportRaid],
            'GuildIncidentReportFalseAlarm' => [39, MessageType::GuildIncidentReportFalseAlarm],
            'PurchaseNotification' => [44, MessageType::PurchaseNotification],
            'PollResult' => [46, MessageType::PollResult],
        ];
    }

    #[Test]
    #[DataProvider('coreWireValues')]
    public function it_resolves_core_discord_message_types(int $value, MessageType $expected): void
    {
        $this->assertSame($expected, MessageType::from($value));
    }

    /**
     * @return array<string, array{int, MessageType}>
     */
    public static function coreWireValues(): array
    {
        return [
            'Default' => [0, MessageType::Default],
            'Reply' => [19, MessageType::Reply],
            'ChatInputCommand' => [20, MessageType::ChatInputCommand],
            'ContextMenuCommand' => [23, MessageType::ContextMenuCommand],
        ];
    }
}
