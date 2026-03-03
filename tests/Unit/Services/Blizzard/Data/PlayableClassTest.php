<?php

namespace Tests\Unit\Services\Blizzard\Data;

use App\Services\Blizzard\Data\PlayableClass;
use App\Services\Blizzard\MediaService;
use App\Services\Blizzard\PlayableClassService;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayableClassTest extends TestCase
{
    #[Test]
    public function from_id_returns_correct_data(): void
    {
        $this->mock(PlayableClassService::class, function (MockInterface $mock) {
            $mock->shouldReceive('find')
                ->once()
                ->with(1)
                ->andReturn(['id' => 1, 'name' => 'Warrior']);

            $mock->shouldReceive('iconUrl')
                ->once()
                ->with(1)
                ->andReturn('https://example.com/warrior.jpg');
        });

        $result = PlayableClass::fromId(1);

        $this->assertSame(1, $result->id);
        $this->assertSame('Warrior', $result->name);
        $this->assertSame('https://example.com/warrior.jpg', $result->icon_url);
    }

    #[Test]
    public function from_id_icon_url_can_be_null(): void
    {
        $this->mock(PlayableClassService::class, function (MockInterface $mock) {
            $mock->shouldReceive('find')
                ->once()
                ->with(2)
                ->andReturn(['id' => 2, 'name' => 'Paladin']);

            $mock->shouldReceive('iconUrl')
                ->once()
                ->with(2)
                ->andReturn(null);
        });

        $result = PlayableClass::fromId(2);

        $this->assertSame(2, $result->id);
        $this->assertSame('Paladin', $result->name);
        $this->assertNull($result->icon_url);
    }

    #[Test]
    public function from_id_falls_back_to_unknown_class_name_when_name_missing(): void
    {
        $this->mock(PlayableClassService::class, function (MockInterface $mock) {
            $mock->shouldReceive('find')->andReturn([]);
            $mock->shouldReceive('iconUrl')->andReturn(null);
        });

        $result = PlayableClass::fromId(99);

        $this->assertSame('Unknown Class', $result->name);
    }

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
