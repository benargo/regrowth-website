<?php

namespace Tests\Unit\Services\Discord\Enums;

use App\Services\Discord\Enums\MessageType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
        ];
    }
}
