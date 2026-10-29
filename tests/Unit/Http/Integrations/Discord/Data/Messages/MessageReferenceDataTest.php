<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Data\Messages\MessageReferenceData;
use App\Http\Integrations\Discord\Enums\MessageReferenceType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\Support\Data\AssertsDataValidation;
use Tests\TestCase;

#[Group('discord-integration')]
class MessageReferenceDataTest extends TestCase
{
    use AssertsDataValidation;

    #[Test]
    public function it_leaves_every_omitted_field_optional(): void
    {
        $reference = MessageReferenceData::from([]);

        $this->assertInstanceOf(Optional::class, $reference->type);
        $this->assertInstanceOf(Optional::class, $reference->messageId);
        $this->assertInstanceOf(Optional::class, $reference->channelId);
        $this->assertInstanceOf(Optional::class, $reference->guildId);
        $this->assertInstanceOf(Optional::class, $reference->failIfNotExists);
    }

    #[Test]
    public function it_hydrates_every_field_from_snake_case_keys(): void
    {
        $reference = MessageReferenceData::from([
            'type' => 0,
            'message_id' => '111122223333444455',
            'channel_id' => '555566667777888899',
            'guild_id' => '999900001111222233',
            'fail_if_not_exists' => false,
        ]);

        $this->assertSame(MessageReferenceType::Default, $reference->type);
        $this->assertSame('111122223333444455', $reference->messageId);
        $this->assertSame('555566667777888899', $reference->channelId);
        $this->assertSame('999900001111222233', $reference->guildId);
        $this->assertFalse($reference->failIfNotExists);
    }

    #[Test]
    public function it_resolves_the_forward_type(): void
    {
        $reference = MessageReferenceData::from(['type' => 1]);

        $this->assertSame(MessageReferenceType::Forward, $reference->type);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_null_message_id(): void
    {
        $this->assertDataRejects(MessageReferenceData::class, ['message_id' => null], 'message_id');
    }
}
