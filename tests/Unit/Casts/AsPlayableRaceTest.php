<?php

namespace Tests\Unit\Casts;

use App\Casts\AsPlayableRace;
use App\Services\Blizzard\ValueObjects\PlayableRace;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

class AsPlayableRaceTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(int $id = 2, string $name = 'Orc'): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'gender_name' => ['male' => $name, 'female' => $name],
            'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
            'is_selectable' => true,
            'is_allied_race' => false,
            'playable_classes' => [],
            'racial_spells' => [],
        ];
    }

    #[Test]
    public function get_returns_unknown_array_when_value_is_null(): void
    {
        $cast = new AsPlayableRace;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'playable_race', null, []);

        $this->assertSame([
            'id' => null,
            'name' => 'Unknown Race',
        ], $result);
    }

    #[Test]
    public function get_decodes_json_string_to_array(): void
    {
        $cast = new AsPlayableRace;
        $model = $this->createStub(Model::class);
        $stored = json_encode(['id' => 2, 'name' => 'Orc']);

        $result = $cast->get($model, 'playable_race', $stored, []);

        $this->assertSame(2, $result['id']);
        $this->assertSame('Orc', $result['name']);
    }

    #[Test]
    public function get_passes_through_already_decoded_array(): void
    {
        $cast = new AsPlayableRace;
        $model = $this->createStub(Model::class);
        $value = ['id' => 2, 'name' => 'Orc'];

        $result = $cast->get($model, 'playable_race', $value, []);

        $this->assertSame($value, $result);
    }

    #[Test]
    public function set_returns_null_when_value_is_null(): void
    {
        $cast = new AsPlayableRace;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'playable_race', null, []);

        $this->assertNull($result);
    }

    #[Test]
    public function set_throws_invalid_argument_on_unexpected_type(): void
    {
        $cast = new AsPlayableRace;
        $model = $this->createStub(Model::class);

        $this->expectException(InvalidArgumentException::class);

        $cast->set($model, 'playable_race', new stdClass, []);
    }

    #[Test]
    public function set_throws_invalid_argument_on_array_input(): void
    {
        $cast = new AsPlayableRace;
        $model = $this->createStub(Model::class);

        $this->expectException(InvalidArgumentException::class);

        $cast->set($model, 'playable_race', ['id' => 2, 'name' => 'Orc'], []);
    }

    #[Test]
    public function set_encodes_vo_as_id_and_name_json(): void
    {
        $cast = new AsPlayableRace;
        $model = $this->createStub(Model::class);
        $vo = PlayableRace::fromApiResponse($this->sampleApiResponse(2, 'Orc'));

        $result = $cast->set($model, 'playable_race', $vo, []);

        $this->assertSame(json_encode([
            'id' => 2,
            'name' => 'Orc',
        ]), $result);
    }
}
