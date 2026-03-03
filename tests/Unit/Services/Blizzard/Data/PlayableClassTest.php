<?php

namespace Tests\Unit\Services\Blizzard\Data;

use App\Services\Blizzard\Data\PlayableClass;
use App\Services\Blizzard\MediaService;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayableClassTest extends TestCase
{
    #[Test]
    public function unknown_returns_null_id_and_unknown_class_name(): void
    {
        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getIconUrlByName')->andReturn(null);
        });

        $result = PlayableClass::unknown();

        $this->assertNull($result->id);
        $this->assertSame('Unknown Class', $result->name);
    }

    #[Test]
    public function unknown_icon_url_uses_media_service(): void
    {
        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getIconUrlByName')
                ->once()
                ->with('inv_misc_questionmark')
                ->andReturn('https://example.com/question.jpg');
        });

        $result = PlayableClass::unknown();

        $this->assertSame('https://example.com/question.jpg', $result->icon_url);
    }

    #[Test]
    public function to_array_returns_expected_keys(): void
    {
        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getIconUrlByName')->andReturn(null);
        });

        $result = PlayableClass::unknown()->toArray();

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('icon_url', $result);
        $this->assertCount(3, $result);
    }
}
