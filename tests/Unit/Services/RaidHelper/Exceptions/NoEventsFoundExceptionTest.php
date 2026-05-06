<?php

namespace Tests\Unit\Services\RaidHelper\Exceptions;

use App\Services\RaidHelper\Exceptions\NoEventsFoundException;
use Exception;
use PHPUnit\Framework\TestCase;

class NoEventsFoundExceptionTest extends TestCase
{
    public function test_it_is_an_exception(): void
    {
        $exception = new NoEventsFoundException;

        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_it_has_the_correct_default_message(): void
    {
        $exception = new NoEventsFoundException;

        $this->assertSame('No events found for the specified server.', $exception->getMessage());
    }

    public function test_it_can_be_thrown_and_caught(): void
    {
        $this->expectException(NoEventsFoundException::class);
        $this->expectExceptionMessage('No events found for the specified server.');

        throw new NoEventsFoundException;
    }
}
