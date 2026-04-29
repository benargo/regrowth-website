<?php

namespace Tests\Unit\Services\Discord\Payloads;

use App\Services\Discord\Payloads\ChannelMessagesQueryString;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChannelMessagesQueryStringTest extends TestCase
{
    #[Test]
    public function it_creates_with_no_parameters(): void
    {
        $payload = ChannelMessagesQueryString::validateAndCreate([]);

        $this->assertNull($payload->around);
        $this->assertNull($payload->before);
        $this->assertNull($payload->after);
        $this->assertSame(50, $payload->limit);
    }

    #[Test]
    public function it_creates_with_only_around(): void
    {
        $payload = ChannelMessagesQueryString::validateAndCreate(['around' => '123456789']);

        $this->assertSame('123456789', $payload->around);
        $this->assertNull($payload->before);
        $this->assertNull($payload->after);
    }

    #[Test]
    public function it_creates_with_only_before(): void
    {
        $payload = ChannelMessagesQueryString::validateAndCreate(['before' => '123456789']);

        $this->assertNull($payload->around);
        $this->assertSame('123456789', $payload->before);
        $this->assertNull($payload->after);
    }

    #[Test]
    public function it_creates_with_only_after(): void
    {
        $payload = ChannelMessagesQueryString::validateAndCreate(['after' => '123456789']);

        $this->assertNull($payload->around);
        $this->assertNull($payload->before);
        $this->assertSame('123456789', $payload->after);
    }

    #[Test]
    public function it_accepts_a_custom_limit(): void
    {
        $payload = ChannelMessagesQueryString::validateAndCreate(['limit' => 100]);

        $this->assertSame(100, $payload->limit);
    }

    #[Test]
    public function it_fails_when_around_and_before_are_both_provided(): void
    {
        $this->expectException(ValidationException::class);

        ChannelMessagesQueryString::validateAndCreate([
            'around' => '123456789',
            'before' => '987654321',
        ]);
    }

    #[Test]
    public function it_fails_when_around_and_after_are_both_provided(): void
    {
        $this->expectException(ValidationException::class);

        ChannelMessagesQueryString::validateAndCreate([
            'around' => '123456789',
            'after' => '987654321',
        ]);
    }

    #[Test]
    public function it_fails_when_before_and_after_are_both_provided(): void
    {
        $this->expectException(ValidationException::class);

        ChannelMessagesQueryString::validateAndCreate([
            'before' => '123456789',
            'after' => '987654321',
        ]);
    }

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
}
