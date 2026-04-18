<?php

namespace Tests\Unit\Casts;

use App\Casts\AsExpansion;
use App\Services\WarcraftLogs\ValueObjects\Expansion;
use App\Services\WarcraftLogs\ValueObjects\Zone;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

class AsExpansionTest extends TestCase
{
    private function sampleJson(): string
    {
        return json_encode([
            'id' => 9,
            'name' => 'The War Within',
            'zones' => [
                ['id' => 43, 'name' => 'Nerub-ar Palace', 'difficulties' => [], 'frozen' => false, 'expansion' => null],
                ['id' => 44, 'name' => 'Blackrock Depths', 'difficulties' => [], 'frozen' => false, 'expansion' => null],
            ],
        ]);
    }

    #[Test]
    public function get_returns_expansion_from_json_string(): void
    {
        $cast = new AsExpansion;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'expansion', $this->sampleJson(), []);

        $this->assertInstanceOf(Expansion::class, $result);
        $this->assertSame(9, $result->id);
        $this->assertSame('The War Within', $result->name);
    }

    #[Test]
    public function get_maps_zones_correctly(): void
    {
        $cast = new AsExpansion;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'expansion', $this->sampleJson(), []);

        $this->assertCount(2, $result->zones);
        $this->assertContainsOnlyInstancesOf(Zone::class, $result->zones);
        $this->assertSame(43, $result->zones[0]->id);
        $this->assertSame('Nerub-ar Palace', $result->zones[0]->name);
        $this->assertSame(44, $result->zones[1]->id);
        $this->assertSame('Blackrock Depths', $result->zones[1]->name);
    }

    #[Test]
    public function get_defaults_zones_to_empty_array_when_not_present(): void
    {
        $cast = new AsExpansion;
        $model = $this->createStub(Model::class);
        $json = json_encode(['id' => 9, 'name' => 'The War Within']);

        $result = $cast->get($model, 'expansion', $json, []);

        $this->assertSame([], $result->zones);
    }

    #[Test]
    public function get_returns_null_when_value_is_null(): void
    {
        $cast = new AsExpansion;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'expansion', null, []);

        $this->assertNull($result);
    }

    #[Test]
    public function set_encodes_expansion_to_json(): void
    {
        $cast = new AsExpansion;
        $model = $this->createStub(Model::class);
        $expansion = new Expansion(
            id: 9,
            name: 'The War Within',
            zones: [
                new Zone(id: 43, name: 'Nerub-ar Palace'),
                new Zone(id: 44, name: 'Blackrock Depths'),
            ],
        );

        $result = $cast->set($model, 'expansion', $expansion, []);

        $this->assertSame($this->sampleJson(), $result);
    }

    #[Test]
    public function set_encodes_expansion_with_no_zones_to_json(): void
    {
        $cast = new AsExpansion;
        $model = $this->createStub(Model::class);
        $expansion = new Expansion(id: 9, name: 'The War Within');

        $result = $cast->set($model, 'expansion', $expansion, []);

        $this->assertSame(json_encode(['id' => 9, 'name' => 'The War Within', 'zones' => []]), $result);
    }

    #[Test]
    public function set_returns_null_when_value_is_null(): void
    {
        $cast = new AsExpansion;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'expansion', null, []);

        $this->assertNull($result);
    }

    #[Test]
    public function set_throws_invalid_argument_when_value_is_not_an_expansion(): void
    {
        $cast = new AsExpansion;
        $model = $this->createStub(Model::class);

        $this->expectException(InvalidArgumentException::class);

        $cast->set($model, 'expansion', new stdClass, []);
    }
}
