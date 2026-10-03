<?php

namespace Tests\Unit\Services\WarcraftLogs\Exceptions;

use App\Services\WarcraftLogs\Exceptions\GuildNotFoundException;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('warcraftlogs-integration')]
class GuildNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function it_extends_the_base_exception(): void
    {
        $this->assertInstanceOf(Exception::class, new GuildNotFoundException);
    }

    #[Test]
    public function it_has_a_default_message_and_code(): void
    {
        $exception = new GuildNotFoundException;

        $this->assertSame('Guild not found', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    #[Test]
    public function it_accepts_a_custom_message_and_code(): void
    {
        $exception = new GuildNotFoundException('No such guild', 404);

        $this->assertSame('No such guild', $exception->getMessage());
        $this->assertSame(404, $exception->getCode());
    }

    #[Test]
    public function it_preserves_the_previous_exception(): void
    {
        $previous = new RuntimeException('upstream failure');

        $exception = new GuildNotFoundException(previous: $previous);

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame('Guild not found', $exception->getMessage());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_can_be_thrown_and_caught(): void
    {
        $this->expectException(GuildNotFoundException::class);
        $this->expectExceptionMessage('Guild not found');

        throw new GuildNotFoundException;
    }
}
