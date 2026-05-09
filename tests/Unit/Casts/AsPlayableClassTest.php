<?php

namespace Tests\Unit\Casts;

use App\Casts\AsPlayableClass;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use App\Services\Blizzard\ValueObjects\PlayableClassData;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

class AsPlayableClassTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(int $id = 7, string $name = 'Shaman'): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'gender_name' => ['male' => $name, 'female' => $name],
            'power_type' => [],
            'media' => ['key' => ['href' => 'https://example.com/media'], 'id' => $id],
            'pvp_talent_slots' => [],
            'playable_races' => [],
        ];
    }

    /**
     * @return array{id: int, assets: array<int, array{key: string, value: string, file_data_id: int}>}
     */
    private function sampleMediaResponse(int $id = 7): array
    {
        return [
            'id' => $id,
            'assets' => [
                [
                    'key' => 'icon',
                    'value' => "https://render.worldofwarcraft.com/eu/icons/56/class_{$id}.jpg",
                    'file_data_id' => 100 + $id,
                ],
            ],
        ];
    }

    #[Test]
    public function get_returns_unknown_array_when_value_is_null(): void
    {
        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->once()
                ->with('inv_misc_questionmark')
                ->andReturn('https://example.com/question.jpg');
        });

        $cast = new AsPlayableClass;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'playable_class', null, []);

        $this->assertSame([
            'id' => null,
            'name' => 'Unknown Class',
            'icon_url' => 'https://example.com/question.jpg',
        ], $result);
    }

    #[Test]
    public function get_decodes_json_string_to_array(): void
    {
        $cast = new AsPlayableClass;
        $model = $this->createStub(Model::class);
        $stored = json_encode(['id' => 7, 'name' => 'Shaman', 'icon_url' => 'https://example.com/shaman.jpg']);

        $result = $cast->get($model, 'playable_class', $stored, []);

        $this->assertSame(7, $result['id']);
        $this->assertSame('Shaman', $result['name']);
        $this->assertSame('https://example.com/shaman.jpg', $result['icon_url']);
    }

    #[Test]
    public function get_passes_through_already_decoded_array(): void
    {
        $cast = new AsPlayableClass;
        $model = $this->createStub(Model::class);
        $value = ['id' => 7, 'name' => 'Shaman', 'icon_url' => null];

        $result = $cast->get($model, 'playable_class', $value, []);

        $this->assertSame($value, $result);
    }

    #[Test]
    public function set_returns_null_when_value_is_null(): void
    {
        $cast = new AsPlayableClass;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'playable_class', null, []);

        $this->assertNull($result);
    }

    #[Test]
    public function set_throws_invalid_argument_on_unexpected_type(): void
    {
        $cast = new AsPlayableClass;
        $model = $this->createStub(Model::class);

        $this->expectException(InvalidArgumentException::class);

        $cast->set($model, 'playable_class', new stdClass, []);
    }

    #[Test]
    public function set_throws_invalid_argument_on_array_input(): void
    {
        $cast = new AsPlayableClass;
        $model = $this->createStub(Model::class);

        $this->expectException(InvalidArgumentException::class);

        $cast->set($model, 'playable_class', ['id' => 1, 'name' => 'Warrior'], []);
    }

    #[Test]
    public function set_encodes_vo_with_resolved_icon_url(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getPlayableClassMedia')
                ->once()
                ->with(7)
                ->andReturn($this->sampleMediaResponse(7));
        });

        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->once()
                ->andReturn([107 => 'https://cdn.local/class_7.jpg']);
        });

        $cast = new AsPlayableClass;
        $model = $this->createStub(Model::class);
        $vo = PlayableClassData::from($this->sampleApiResponse(7, 'Shaman'));

        $result = $cast->set($model, 'playable_class', $vo, []);

        $this->assertSame(json_encode([
            'id' => 7,
            'name' => 'Shaman',
            'icon_url' => 'https://cdn.local/class_7.jpg',
        ]), $result);
    }

    #[Test]
    public function set_stores_null_icon_url_when_media_has_no_assets(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getPlayableClassMedia')
                ->once()
                ->with(7)
                ->andReturn(['id' => 7, 'assets' => []]);
        });

        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('get');
        });

        $cast = new AsPlayableClass;
        $model = $this->createStub(Model::class);
        $vo = PlayableClassData::from($this->sampleApiResponse(7, 'Shaman'));

        $result = $cast->set($model, 'playable_class', $vo, []);

        $this->assertSame(json_encode([
            'id' => 7,
            'name' => 'Shaman',
            'icon_url' => null,
        ]), $result);
    }

    #[Test]
    public function set_stores_null_icon_url_when_media_service_returns_null(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getPlayableClassMedia')
                ->once()
                ->with(7)
                ->andReturn($this->sampleMediaResponse(7));
        });

        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->once()
                ->andReturn([107 => null]);
        });

        $cast = new AsPlayableClass;
        $model = $this->createStub(Model::class);
        $vo = PlayableClassData::from($this->sampleApiResponse(7, 'Shaman'));

        $result = $cast->set($model, 'playable_class', $vo, []);

        $this->assertSame(json_encode([
            'id' => 7,
            'name' => 'Shaman',
            'icon_url' => null,
        ]), $result);
    }
}
