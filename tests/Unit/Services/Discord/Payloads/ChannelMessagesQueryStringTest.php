<?php

namespace Tests\Unit\Services\Discord\Payloads;

use App\Services\Discord\Enums\MessageType;
use App\Services\Discord\Payloads\ChannelMessagesQueryString;
use App\Services\Discord\Resources\Message;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('discord-integration')]
class ChannelMessagesQueryStringTest extends TestCase
{
    #[Test]
    public function channel_messages_query_string_can_be_constructed_directly(): void
    {
        $query = new ChannelMessagesQueryString(
            around: Optional::create(),
            before: Optional::create(),
            after: Optional::create(),
            limit: 50,
        );

        $this->assertInstanceOf(Optional::class, $query->around);
        $this->assertInstanceOf(Optional::class, $query->before);
        $this->assertInstanceOf(Optional::class, $query->after);
        $this->assertSame(50, $query->limit);
    }

    #[Test]
    public function it_creates_with_no_parameters(): void
    {
        $payload = ChannelMessagesQueryString::validateAndCreate([]);

        $this->assertInstanceOf(Optional::class, $payload->around);
        $this->assertInstanceOf(Optional::class, $payload->before);
        $this->assertInstanceOf(Optional::class, $payload->after);
        $this->assertSame(50, $payload->limit);
    }

    #[Test]
    public function it_creates_with_only_around(): void
    {
        $payload = ChannelMessagesQueryString::validateAndCreate(['around' => '123456789']);

        $this->assertSame('123456789', $payload->around);
        $this->assertInstanceOf(Optional::class, $payload->before);
        $this->assertInstanceOf(Optional::class, $payload->after);
    }

    #[Test]
    public function it_creates_with_only_before(): void
    {
        $payload = ChannelMessagesQueryString::validateAndCreate(['before' => '123456789']);

        $this->assertInstanceOf(Optional::class, $payload->around);
        $this->assertSame('123456789', $payload->before);
        $this->assertInstanceOf(Optional::class, $payload->after);
    }

    #[Test]
    public function it_creates_with_only_after(): void
    {
        $payload = ChannelMessagesQueryString::validateAndCreate(['after' => '123456789']);

        $this->assertInstanceOf(Optional::class, $payload->around);
        $this->assertInstanceOf(Optional::class, $payload->before);
        $this->assertSame('123456789', $payload->after);
    }

    #[Test]
    public function it_accepts_a_custom_limit(): void
    {
        $payload = ChannelMessagesQueryString::validateAndCreate(['limit' => 100]);

        $this->assertSame(100, $payload->limit);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_fails_when_around_and_before_are_both_provided(): void
    {
        $this->expectException(ValidationException::class);

        ChannelMessagesQueryString::validateAndCreate([
            'around' => '123456789',
            'before' => '987654321',
        ]);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_fails_when_around_and_after_are_both_provided(): void
    {
        $this->expectException(ValidationException::class);

        ChannelMessagesQueryString::validateAndCreate([
            'around' => '123456789',
            'after' => '987654321',
        ]);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_fails_when_before_and_after_are_both_provided(): void
    {
        $this->expectException(ValidationException::class);

        ChannelMessagesQueryString::validateAndCreate([
            'before' => '123456789',
            'after' => '987654321',
        ]);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_fails_when_all_three_are_provided(): void
    {
        $this->expectException(ValidationException::class);

        ChannelMessagesQueryString::validateAndCreate([
            'around' => '111',
            'before' => '222',
            'after' => '333',
        ]);
    }

    #[Test]
    public function it_adds_the_error_to_the_around_key(): void
    {
        try {
            ChannelMessagesQueryString::validateAndCreate([
                'around' => '111',
                'before' => '222',
            ]);

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('around', $e->errors());
            $this->assertStringContainsString('Only one of around, before, or after may be specified at a time.', $e->errors()['around'][0]);
        }
    }

    #[Test]
    public function it_accepts_a_message_resource_for_before_and_flattens_to_id(): void
    {
        $message = $this->makeMessage('999');

        $data = new ChannelMessagesQueryString(before: $message);

        $this->assertSame('999', $data->toArray()['before']);
    }

    #[Test]
    public function it_accepts_a_message_resource_for_after_and_flattens_to_id(): void
    {
        $message = $this->makeMessage('888');

        $data = new ChannelMessagesQueryString(after: $message);

        $this->assertSame('888', $data->toArray()['after']);
    }

    #[Test]
    public function it_accepts_a_message_resource_for_around_and_flattens_to_id(): void
    {
        $message = $this->makeMessage('777');

        $data = new ChannelMessagesQueryString(around: $message);

        $this->assertSame('777', $data->toArray()['around']);
    }

    #[Test]
    public function it_accepts_a_string_snowflake_for_cursor_fields(): void
    {
        $data = new ChannelMessagesQueryString(before: '12345');

        $this->assertSame('12345', $data->toArray()['before']);
    }

    private function makeMessage(string $id): Message
    {
        return Message::from([
            'id' => $id,
            'channel_id' => '1',
            'timestamp' => '2024-01-01T00:00:00Z',
            'tts' => false,
            'mention_everyone' => false,
            'mention_roles' => [],
            'attachments' => [],
            'embeds' => [],
            'pinned' => false,
            'type' => MessageType::Default->value,
        ]);
    }
}
