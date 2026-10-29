<?php

namespace Tests\Unit\Http\Integrations\Discord\Enums;

use App\Http\Integrations\Discord\Enums\ChannelType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('discord-integration')]
#[Group('platform')]
class ChannelTypeTest extends TestCase
{
    #[Test]
    public function thread_types_lists_the_three_thread_cases(): void
    {
        $this->assertSame(
            [ChannelType::AnnouncementThread, ChannelType::PublicThread, ChannelType::PrivateThread],
            ChannelType::threadTypes(),
        );
    }

    #[Test]
    #[DataProvider('everyType')]
    public function is_thread_is_true_only_for_thread_types(ChannelType $type, bool $expected): void
    {
        $this->assertSame($expected, $type->isThread());
    }

    /**
     * @return array<string, array{ChannelType, bool}>
     */
    public static function everyType(): array
    {
        return collect(ChannelType::cases())
            ->mapWithKeys(fn (ChannelType $type): array => [
                $type->name => [$type, in_array($type->value, [10, 11, 12], true)],
            ])
            ->all();
    }
}
