<?php

namespace Tests\Unit\Casts;

use App\Casts\AsDifficultyCollection;
use App\Services\WarcraftLogs\ValueObjects\Difficulty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

class AsDifficultyCollectionTest extends TestCase
{
    private function sampleJson(): string
    {
        return json_encode([
            ['id' => 3, 'name' => 'Normal', 'sizes' => [10, 25]],
            ['id' => 4, 'name' => 'Heroic', 'sizes' => [10, 25]],
        ]);
    }

    #[Test]
    public function get_returns_collection_of_difficulty_objects(): void
    {
        $cast = new AsDifficultyCollection;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'difficulties', $this->sampleJson(), []);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(Difficulty::class, $result);
    }

    #[Test]
    public function get_maps_fields_correctly(): void
    {
        $cast = new AsDifficultyCollection;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'difficulties', $this->sampleJson(), []);

        $first = $result->first();
        $this->assertSame(3, $first->id);
        $this->assertSame('Normal', $first->name);
        $this->assertSame([10, 25], $first->sizes);

        $second = $result->last();
        $this->assertSame(4, $second->id);
        $this->assertSame('Heroic', $second->name);
        $this->assertSame([10, 25], $second->sizes);
    }

    #[Test]
    public function get_defaults_sizes_to_empty_array_when_not_present(): void
    {
        $cast = new AsDifficultyCollection;
        $model = $this->createStub(Model::class);
        $json = json_encode([['id' => 1, 'name' => 'Normal']]);

        $result = $cast->get($model, 'difficulties', $json, []);

        $this->assertSame([], $result->first()->sizes);
    }

    #[Test]
    public function get_returns_empty_collection_for_empty_array(): void
    {
        $cast = new AsDifficultyCollection;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'difficulties', '[]', []);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function set_encodes_collection_to_json(): void
    {
        $cast = new AsDifficultyCollection;
        $model = $this->createStub(Model::class);
        $collection = collect([
            new Difficulty(id: 3, name: 'Normal', sizes: [10, 25]),
            new Difficulty(id: 4, name: 'Heroic', sizes: [10, 25]),
        ]);

        $result = $cast->set($model, 'difficulties', $collection, []);

        $this->assertSame($this->sampleJson(), $result);
    }

    #[Test]
    public function set_accepts_plain_array_of_difficulties(): void
    {
        $cast = new AsDifficultyCollection;
        $model = $this->createStub(Model::class);
        $array = [new Difficulty(id: 3, name: 'Normal', sizes: [10, 25])];

        $result = $cast->set($model, 'difficulties', $array, []);

        $this->assertSame(
            json_encode([['id' => 3, 'name' => 'Normal', 'sizes' => [10, 25]]]),
            $result,
        );
    }

    #[Test]
    public function set_throws_invalid_argument_when_item_is_not_a_difficulty(): void
    {
        $cast = new AsDifficultyCollection;
        $model = $this->createStub(Model::class);

        $this->expectException(InvalidArgumentException::class);

        $cast->set($model, 'difficulties', collect([new stdClass]), []);
    }

    #[Test]
    public function set_encodes_empty_collection_to_empty_json_array(): void
    {
        $cast = new AsDifficultyCollection;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'difficulties', collect(), []);

        $this->assertSame('[]', $result);
    }
}
