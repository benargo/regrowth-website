<?php

namespace Tests\Unit\Http\Integrations\Discord\Enums;

use App\Http\Integrations\Discord\Enums\AttachmentFlag;
use App\Http\Integrations\Discord\Enums\ChannelType;
use App\Http\Integrations\Discord\Enums\MessageFlag;
use App\Http\Integrations\Discord\Enums\PremiumType;
use App\Http\Integrations\Discord\Enums\UserFlag;
use BackedEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('discord-integration')]
#[Group('contract')]
class EnumCaseNamesTest extends TestCase
{
    /**
     * @param  class-string<BackedEnum>  $enum
     * @param  array<string, int>  $expected
     */
    #[Test]
    #[DataProvider('renamedEnums')]
    public function it_names_every_case_in_pascal_case_and_keeps_discords_values(string $enum, array $expected): void
    {
        $this->assertSame($expected, array_column($enum::cases(), 'value', 'name'));
    }

    /**
     * @return array<string, array{class-string<BackedEnum>, array<string, int>}>
     */
    public static function renamedEnums(): array
    {
        return [
            'ChannelType' => [ChannelType::class, [
                'GuildText' => 0, 'Dm' => 1, 'GuildVoice' => 2, 'GroupDm' => 3, 'GuildCategory' => 4,
                'GuildAnnouncement' => 5, 'AnnouncementThread' => 10, 'PublicThread' => 11, 'PrivateThread' => 12,
                'GuildStageVoice' => 13, 'GuildDirectory' => 14, 'GuildForum' => 15, 'GuildMedia' => 16,
            ]],
            'AttachmentFlag' => [AttachmentFlag::class, [
                'IsClip' => 1, 'IsThumbnail' => 2, 'IsRemix' => 4, 'IsSpoiler' => 8, 'IsAnimated' => 16,
            ]],
            'MessageFlag' => [MessageFlag::class, [
                'Crossposted' => 1, 'IsCrosspost' => 2, 'SuppressEmbeds' => 4, 'SourceMessageDeleted' => 8,
                'Urgent' => 16, 'HasThread' => 32, 'Ephemeral' => 64, 'Loading' => 128,
                'FailedToMentionSomeRolesInThread' => 256, 'SuppressNotifications' => 4096,
                'IsVoiceMessage' => 8192, 'HasSnapshot' => 16384, 'IsComponentsV2' => 32768,
            ]],
            'PremiumType' => [PremiumType::class, [
                'None' => 0, 'NitroClassic' => 1, 'Nitro' => 2, 'NitroBasic' => 3,
            ]],
            'UserFlag' => [UserFlag::class, [
                'Staff' => 1, 'Partner' => 2, 'Hypesquad' => 4, 'BugHunterLevel1' => 8,
                'HypesquadOnlineHouse1' => 64, 'HypesquadOnlineHouse2' => 128, 'HypesquadOnlineHouse3' => 256,
                'PremiumEarlySupporter' => 512, 'TeamPseudoUser' => 1024, 'BugHunterLevel2' => 16384,
                'VerifiedBot' => 65536, 'VerifiedDeveloper' => 131072, 'CertifiedModerator' => 262144,
                'BotHttpInteractions' => 524288,
            ]],
        ];
    }
}
