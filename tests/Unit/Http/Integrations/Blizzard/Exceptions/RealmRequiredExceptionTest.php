<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Exceptions;

use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Exceptions\RealmRequiredException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
class RealmRequiredExceptionTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_includes_the_namespace_value_in_the_message(): void
    {
        $exception = new RealmRequiredException(BlizzardNamespace::RETAIL);

        $this->assertSame(
            'A realm is required for the "retail" Blizzard namespace.',
            $exception->getMessage(),
        );
    }

    #[Test]
    #[Group('happy-path')]
    public function it_is_an_invalid_argument_exception(): void
    {
        $exception = new RealmRequiredException(BlizzardNamespace::ANNIVERSARY);

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_is_not_a_blizzard_request_exception(): void
    {
        $exception = new RealmRequiredException(BlizzardNamespace::ANNIVERSARY);

        $this->assertNotInstanceOf(BlizzardRequestException::class, $exception);
    }
}
