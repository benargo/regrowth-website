<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Data\Messages\EmbedData;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Http\Integrations\Discord\Data\Messages\MessageReferenceData;
use App\Http\Integrations\Discord\Data\Messages\PartialAttachmentData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\Support\Data\AssertsDataValidation;
use Tests\TestCase;

#[Group('discord-integration')]
class MessagePayloadDataTest extends TestCase
{
    use AssertsDataValidation;

    #[Test]
    public function it_leaves_every_omitted_field_optional(): void
    {
        $payload = MessagePayloadData::from([]);

        $this->assertInstanceOf(Optional::class, $payload->content);
        $this->assertInstanceOf(Optional::class, $payload->nonce);
        $this->assertInstanceOf(Optional::class, $payload->tts);
        $this->assertInstanceOf(Optional::class, $payload->embeds);
        $this->assertInstanceOf(Optional::class, $payload->messageReference);
        $this->assertInstanceOf(Optional::class, $payload->stickerIds);
        $this->assertInstanceOf(Optional::class, $payload->payloadJson);
        $this->assertInstanceOf(Optional::class, $payload->attachments);
        $this->assertInstanceOf(Optional::class, $payload->flags);
        $this->assertInstanceOf(Optional::class, $payload->enforceNonce);
    }

    #[Test]
    public function it_hydrates_every_scalar_field(): void
    {
        $payload = MessagePayloadData::from([
            'content' => 'Hello, world!',
            'nonce' => '987654321',
            'tts' => true,
            'sticker_ids' => ['111', '222'],
            'payload_json' => '{}',
            'flags' => 4,
            'enforce_nonce' => true,
        ]);

        $this->assertSame('Hello, world!', $payload->content);
        $this->assertSame('987654321', $payload->nonce);
        $this->assertTrue($payload->tts);
        $this->assertSame(['111', '222'], $payload->stickerIds);
        $this->assertSame('{}', $payload->payloadJson);
        $this->assertSame(4, $payload->flags);
        $this->assertTrue($payload->enforceNonce);
    }

    #[Test]
    public function it_stores_an_integer_nonce(): void
    {
        $payload = MessagePayloadData::from(['nonce' => 12345]);

        $this->assertSame(12345, $payload->nonce);
    }

    // ==================== nested objects ====================

    #[Test]
    public function it_hydrates_embeds_into_a_list_of_embed_data(): void
    {
        $payload = MessagePayloadData::from([
            'embeds' => [
                ['title' => 'First embed'],
                ['title' => 'Second embed'],
            ],
        ]);

        $this->assertCount(2, $payload->embeds);
        $this->assertInstanceOf(EmbedData::class, $payload->embeds[0]);
        $this->assertSame('First embed', $payload->embeds[0]->title);
        $this->assertSame('Second embed', $payload->embeds[1]->title);
    }

    #[Test]
    public function it_hydrates_the_message_reference(): void
    {
        $payload = MessagePayloadData::from([
            'message_reference' => ['message_id' => '111122223333444455'],
        ]);

        $this->assertInstanceOf(MessageReferenceData::class, $payload->messageReference);
        $this->assertSame('111122223333444455', $payload->messageReference->messageId);
    }

    #[Test]
    public function it_hydrates_partial_upload_attachments(): void
    {
        $payload = MessagePayloadData::from([
            'attachments' => [
                ['id' => 0, 'filename' => 'file.png', 'description' => 'A screenshot'],
            ],
        ]);

        $this->assertCount(1, $payload->attachments);
        $this->assertInstanceOf(PartialAttachmentData::class, $payload->attachments[0]);
        $this->assertSame(0, $payload->attachments[0]->id);
        $this->assertSame('file.png', $payload->attachments[0]->filename);
    }

    #[Test]
    public function it_exposes_nested_snake_case_input_through_camel_case_properties(): void
    {
        $payload = MessagePayloadData::from([
            'embeds' => [['footer' => ['text' => 'x', 'icon_url' => 'https://cdn.example.test/a.png']]],
            'message_reference' => ['message_id' => '111111111111111111'],
            'sticker_ids' => ['1'],
        ]);

        $this->assertSame('https://cdn.example.test/a.png', $payload->embeds[0]->footer->iconUrl);
        $this->assertSame('111111111111111111', $payload->messageReference->messageId);
        $this->assertSame(['1'], $payload->stickerIds);
    }

    // ==================== validate ====================

    #[Test]
    #[Group('validation')]
    public function it_rejects_content_over_two_thousand_characters(): void
    {
        $this->assertDataRejects(MessagePayloadData::class, ['content' => str_repeat('a', 2001)], 'content');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_content_that_is_not_a_string(): void
    {
        $this->assertDataRejects(MessagePayloadData::class, ['content' => ['Hello']], 'content');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_null_tts_flag(): void
    {
        $this->assertDataRejects(MessagePayloadData::class, ['tts' => null], 'tts');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_more_than_ten_embeds(): void
    {
        $this->assertDataRejects(MessagePayloadData::class, ['embeds' => array_fill(0, 11, ['title' => 'Embed'])], 'embeds');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_more_than_three_stickers_supplied_under_the_wire_key(): void
    {
        $this->assertDataRejects(MessagePayloadData::class, ['sticker_ids' => ['1', '2', '3', '4']], 'sticker_ids');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_string_nonce_over_twenty_five_characters(): void
    {
        $this->assertDataRejects(MessagePayloadData::class, ['nonce' => str_repeat('a', 26)], 'nonce');
    }

    #[Test]
    #[Group('validation')]
    public function it_accepts_embeds_totalling_exactly_six_thousand_characters(): void
    {
        $payload = MessagePayloadData::validateAndCreate(['embeds' => $this->embedsTotalling(6000)]);

        $this->assertCount(2, $payload->embeds);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_embeds_totalling_more_than_six_thousand_characters(): void
    {
        $this->assertDataRejects(MessagePayloadData::class, ['embeds' => $this->embedsTotalling(6001)], 'embeds');
    }

    // ==================== toArray ====================

    #[Test]
    #[Group('contract')]
    public function it_round_trips_a_legacy_shaped_payload_with_identical_snake_case_keys(): void
    {
        $legacy = [
            'content' => '<@&829020506907869999>',
            'nonce' => 'nonce-1',
            'tts' => false,
            'embeds' => [[
                'title' => "📜 Today's Daily Quests",
                'type' => 'rich',
                'description' => 'Here are the daily quests.',
                'url' => 'https://regrowth.test/daily-quests',
                'timestamp' => '2026-10-13T12:00:00+00:00',
                'color' => 15844367,
                'footer' => ['text' => 'Posted by Thrall.', 'icon_url' => 'https://cdn.example.test/a.png', 'proxy_icon_url' => 'https://proxy.example.test/a.png'],
                'image' => ['url' => 'https://regrowth.test/i.webp', 'proxy_url' => 'https://proxy.example.test/i.webp', 'content_type' => 'image/webp', 'placeholder_version' => 1],
                'author' => ['name' => 'Regrowth', 'icon_url' => 'https://regrowth.test/icon.png'],
                'fields' => [['name' => 'Cooking', 'value' => 'Soup', 'inline' => false]],
            ]],
            'message_reference' => ['message_id' => '111111111111111111', 'channel_id' => '222222222222222222', 'guild_id' => '333333333333333333', 'fail_if_not_exists' => false],
            'sticker_ids' => ['444444444444444444'],
            'attachments' => [[
                'id' => '555555555555555555',
                'filename' => 'file.png',
                'description' => 'A screenshot',
            ]],
            'flags' => 4,
            'enforce_nonce' => true,
        ];

        $this->assertSame($legacy, MessagePayloadData::from($legacy)->toArray());
    }

    // ==================== helpers ====================

    /**
     * Build two embeds whose counted text (title, description, field name and value, footer text, author name) sums to the given length.
     *
     * @return array<int, array<string, mixed>>
     */
    private function embedsTotalling(int $length): array
    {
        $first = [
            'title' => str_repeat('a', 200),
            'description' => str_repeat('b', 2000),
            'fields' => [['name' => str_repeat('c', 100), 'value' => str_repeat('d', 1000)]],
            'footer' => ['text' => str_repeat('e', 500)],
            'author' => ['name' => str_repeat('f', 200)],
        ];

        return [$first, ['description' => str_repeat('g', $length - 4000)]];
    }
}
