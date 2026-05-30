<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Exceptions;

use App\Http\Integrations\Blizzard\Exceptions\MediaNotFoundException;
use App\Http\Integrations\Blizzard\Exceptions\NotFoundException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MediaNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function it_extends_not_found_exception(): void
    {
        $this->assertTrue(is_subclass_of(MediaNotFoundException::class, NotFoundException::class));
    }

    #[Test]
    public function it_uses_a_media_specific_prefix_in_the_message(): void
    {
        $this->assertSame(
            'Media not found:',
            (new ReflectionClass(MediaNotFoundException::class))->getDefaultProperties()['prefix'],
        );
    }
}
