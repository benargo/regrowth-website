<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Data\Messages\PartialAttachmentData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\Support\Data\AssertsDataValidation;
use Tests\TestCase;

#[Group('discord-integration')]
class PartialAttachmentDataTest extends TestCase
{
    use AssertsDataValidation;

    #[Test]
    public function it_hydrates_a_new_upload_referenced_by_file_index(): void
    {
        $attachment = PartialAttachmentData::from([
            'id' => 0,
            'filename' => 'file.png',
            'description' => 'A screenshot',
        ]);

        $this->assertSame(0, $attachment->id);
        $this->assertSame('file.png', $attachment->filename);
        $this->assertSame('A screenshot', $attachment->description);
    }

    #[Test]
    public function it_hydrates_an_existing_attachment_referenced_by_snowflake_only(): void
    {
        $attachment = PartialAttachmentData::from(['id' => '123456789012345678']);

        $this->assertSame('123456789012345678', $attachment->id);
        $this->assertInstanceOf(Optional::class, $attachment->filename);
        $this->assertInstanceOf(Optional::class, $attachment->title);
        $this->assertInstanceOf(Optional::class, $attachment->description);
        $this->assertInstanceOf(Optional::class, $attachment->durationSecs);
        $this->assertInstanceOf(Optional::class, $attachment->waveform);
        $this->assertInstanceOf(Optional::class, $attachment->isSpoiler);
    }

    #[Test]
    public function it_hydrates_the_title_spoiler_flag_and_voice_message_fields(): void
    {
        $attachment = PartialAttachmentData::from([
            'id' => 0,
            'title' => 'Raid recap',
            'duration_secs' => 4.5,
            'waveform' => 'AAAAAA==',
            'is_spoiler' => true,
        ]);

        $this->assertSame('Raid recap', $attachment->title);
        $this->assertSame(4.5, $attachment->durationSecs);
        $this->assertSame('AAAAAA==', $attachment->waveform);
        $this->assertTrue($attachment->isSpoiler);
    }

    #[Test]
    #[Group('contract')]
    public function it_serializes_the_spoiler_flag_under_its_wire_key(): void
    {
        $this->assertSame(['id' => 0, 'is_spoiler' => true], PartialAttachmentData::from(['id' => 0, 'is_spoiler' => true])->toArray());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_description_over_one_thousand_and_twenty_four_characters(): void
    {
        $this->assertDataRejects(PartialAttachmentData::class, ['id' => 0, 'description' => str_repeat('a', 1025)], 'description');
    }
}
