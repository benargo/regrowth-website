<?php

namespace Tests\Unit\Data\Discord;

use App\Data\Discord\MentionChannelData;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class MentionChannelDataTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_serialises_to_snake_case_keys(): void
    {
        $channel = new MentionChannelData(
            id: '1234567890123456789',
            name: 'raid-signups',
            category: 'Raiding',
            isCategory: false,
            isDeleted: false,
            url: 'https://discord.com/channels/9999999999999999999/1234567890123456789',
        );

        $this->assertSame([
            'id' => '1234567890123456789',
            'name' => 'raid-signups',
            'category' => 'Raiding',
            'is_category' => false,
            'is_deleted' => false,
            'url' => 'https://discord.com/channels/9999999999999999999/1234567890123456789',
        ], $channel->toArray());
    }

    #[Test]
    #[Group('contract')]
    public function it_serialises_a_null_category(): void
    {
        $channel = new MentionChannelData(
            id: '1234567890123456789',
            name: 'Archive',
            category: null,
            isCategory: true,
            isDeleted: true,
            url: 'https://discord.com/channels/9999999999999999999/1234567890123456789',
        );

        $this->assertSame([
            'id' => '1234567890123456789',
            'name' => 'Archive',
            'category' => null,
            'is_category' => true,
            'is_deleted' => true,
            'url' => 'https://discord.com/channels/9999999999999999999/1234567890123456789',
        ], $channel->toArray());
    }

    #[Test]
    #[DataProvider('validUrls')]
    public function it_accepts_a_discord_channel_url(string $url): void
    {
        $channel = MentionChannelData::validateAndCreate($this->payload($url));

        $this->assertSame($url, $channel->url);
    }

    #[Test]
    #[DataProvider('invalidUrls')]
    public function it_rejects_a_url_that_is_not_a_discord_channel_link(string $url): void
    {
        $this->expectException(ValidationException::class);

        MentionChannelData::validate($this->payload($url));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validUrls(): array
    {
        return [
            'channel' => ['https://discord.com/channels/9999999999999999999/1234567890123456789'],
            'thread' => ['https://discord.com/channels/9999999999999999999/1234567890123456789/1111111111111111111'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUrls(): array
    {
        return [
            'another host' => ['https://example.com/channels/9999999999999999999/1234567890123456789'],
            'plain http' => ['http://discord.com/channels/9999999999999999999/1234567890123456789'],
            'missing server id' => ['https://discord.com/channels//1234567890123456789'],
            'missing channel id' => ['https://discord.com/channels/9999999999999999999'],
            'non-numeric id' => ['https://discord.com/channels/9999999999999999999/raid-signups'],
            'too many segments' => ['https://discord.com/channels/9999999999999999999/1234567890123456789/1111111111111111111/2222222222222222222'],
        ];
    }

    /**
     * @return array{id: string, name: string, category: null, is_category: bool, is_deleted: bool, url: string}
     */
    private function payload(string $url): array
    {
        return [
            'id' => '1234567890123456789',
            'name' => 'raid-signups',
            'category' => null,
            'is_category' => false,
            'is_deleted' => false,
            'url' => $url,
        ];
    }
}
