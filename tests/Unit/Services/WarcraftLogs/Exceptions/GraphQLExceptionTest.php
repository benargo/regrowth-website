<?php

namespace Tests\Unit\Services\WarcraftLogs\Exceptions;

use App\Services\WarcraftLogs\Exceptions\GraphQLException;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('warcraftlogs-integration')]
class GraphQLExceptionTest extends TestCase
{
    #[Test]
    public function it_extends_the_base_exception(): void
    {
        $this->assertInstanceOf(Exception::class, new GraphQLException([['message' => 'boom']]));
    }

    // ==================== constructor ====================

    #[Test]
    public function it_builds_the_message_from_the_first_error(): void
    {
        $exception = new GraphQLException([
            ['message' => 'Field not found'],
            ['message' => 'Second error'],
        ]);

        $this->assertSame('GraphQL query failed: Field not found', $exception->getMessage());
    }

    #[Test]
    public function it_accepts_a_custom_message_prefix(): void
    {
        $exception = new GraphQLException([['message' => 'Rate limited']], 'Custom failure');

        $this->assertSame('Custom failure: Rate limited', $exception->getMessage());
    }

    #[Test]
    public function it_falls_back_to_unknown_error_when_there_are_no_errors(): void
    {
        $exception = new GraphQLException([]);

        $this->assertSame('GraphQL query failed: Unknown error', $exception->getMessage());
    }

    // ==================== getErrors ====================

    #[Test]
    public function get_errors_returns_all_errors(): void
    {
        $errors = [
            ['message' => 'First', 'path' => ['reportData', 'report'], 'extensions' => ['category' => 'graphql']],
            ['message' => 'Second'],
        ];

        $exception = new GraphQLException($errors);

        $this->assertSame($errors, $exception->getErrors());
    }

    // ==================== getFirstError ====================

    #[Test]
    public function get_first_error_returns_the_first_message(): void
    {
        $exception = new GraphQLException([
            ['message' => 'First'],
            ['message' => 'Second'],
        ]);

        $this->assertSame('First', $exception->getFirstError());
    }

    #[Test]
    public function get_first_error_returns_null_when_there_are_no_errors(): void
    {
        $exception = new GraphQLException([]);

        $this->assertNull($exception->getFirstError());
    }

    // ==================== hasErrorMatching ====================

    #[Test]
    public function has_error_matching_returns_true_when_the_first_error_matches(): void
    {
        $exception = new GraphQLException([['message' => 'Guild does not exist']]);

        $this->assertTrue($exception->hasErrorMatching('/does not exist/'));
    }

    #[Test]
    public function has_error_matching_checks_every_error(): void
    {
        $exception = new GraphQLException([
            ['message' => 'Something else'],
            ['message' => 'You have exceeded the rate limit'],
        ]);

        $this->assertTrue($exception->hasErrorMatching('/rate limit/i'));
    }

    #[Test]
    public function has_error_matching_returns_false_when_nothing_matches(): void
    {
        $exception = new GraphQLException([['message' => 'Something else']]);

        $this->assertFalse($exception->hasErrorMatching('/rate limit/'));
    }

    #[Test]
    public function has_error_matching_returns_false_when_there_are_no_errors(): void
    {
        $exception = new GraphQLException([]);

        $this->assertFalse($exception->hasErrorMatching('/.*/'));
    }

    #[Test]
    public function has_error_matching_treats_a_missing_message_as_an_empty_string(): void
    {
        $exception = new GraphQLException([['path' => ['reportData']]]);

        $this->assertFalse($exception->hasErrorMatching('/.+/'));
        $this->assertTrue($exception->hasErrorMatching('/^$/'));
    }

    // ==================== throwing ====================

    #[Test]
    #[Group('error-handling')]
    public function it_can_be_thrown_and_caught(): void
    {
        $this->expectException(GraphQLException::class);
        $this->expectExceptionMessage('GraphQL query failed: Boom');

        throw new GraphQLException([['message' => 'Boom']]);
    }
}
