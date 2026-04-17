<?php

namespace Tests\Unit\Services\WarcraftLogs\ValueObjects;

use App\Services\WarcraftLogs\ValueObjects\Difficulty;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DifficultyTest extends TestCase
{
    #[Test]
    public function from_array_parses_all_fields(): void
    {
        $difficulty = Difficulty::fromArray(['id' => 4, 'name' => 'Heroic', 'sizes' => [10, 25]]);

        $this->assertSame(4, $difficulty->id);
        $this->assertSame('Heroic', $difficulty->name);
        $this->assertSame([10, 25], $difficulty->sizes);
    }

    #[Test]
    public function from_array_defaults_sizes_to_empty_array(): void
    {
        $difficulty = Difficulty::fromArray(['id' => 1, 'name' => 'Normal']);

        $this->assertSame([], $difficulty->sizes);
    }

    #[Test]
    public function it_stores_all_constructor_properties(): void
    {
        $difficulty = new Difficulty(id: 4, name: 'Heroic', sizes: [10, 25]);

        $this->assertSame(4, $difficulty->id);
        $this->assertSame('Heroic', $difficulty->name);
        $this->assertSame([10, 25], $difficulty->sizes);
    }

    #[Test]
    public function sizes_defaults_to_empty_array(): void
    {
        $difficulty = new Difficulty(id: 1, name: 'Normal');

        $this->assertSame([], $difficulty->sizes);
    }

    #[Test]
    public function it_implements_arrayable(): void
    {
        $difficulty = new Difficulty(id: 3, name: 'Mythic', sizes: [20]);

        $this->assertInstanceOf(Arrayable::class, $difficulty);
    }

    #[Test]
    public function it_implements_json_serializable(): void
    {
        $difficulty = new Difficulty(id: 3, name: 'Mythic', sizes: [20]);

        $this->assertInstanceOf(JsonSerializable::class, $difficulty);
    }

    #[Test]
    public function json_serialize_returns_same_as_to_array(): void
    {
        $difficulty = new Difficulty(id: 4, name: 'Heroic', sizes: [10, 25]);

        $this->assertSame($difficulty->toArray(), $difficulty->jsonSerialize());
    }

    #[Test]
    public function json_encode_produces_correct_output(): void
    {
        $difficulty = new Difficulty(id: 4, name: 'Heroic', sizes: [10, 25]);

        $this->assertSame(
            '{"id":4,"name":"Heroic","sizes":[10,25]}',
            json_encode($difficulty),
        );
    }

    #[Test]
    public function to_array_returns_all_fields(): void
    {
        $difficulty = new Difficulty(id: 4, name: 'Heroic', sizes: [10, 25]);

        $this->assertSame([
            'id' => 4,
            'name' => 'Heroic',
            'sizes' => [10, 25],
        ], $difficulty->toArray());
    }

    #[Test]
    public function to_array_includes_empty_sizes(): void
    {
        $difficulty = new Difficulty(id: 1, name: 'Normal');

        $this->assertSame([
            'id' => 1,
            'name' => 'Normal',
            'sizes' => [],
        ], $difficulty->toArray());
    }
}
