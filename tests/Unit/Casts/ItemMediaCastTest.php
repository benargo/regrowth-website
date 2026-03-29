<?php

namespace Tests\Unit\Casts;

use App\Casts\ItemMediaCast;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemMediaCastTest extends TestCase
{
    /**
     * @return array{id: int, assets: array<int, array{key: string, value: string, file_data_id: int}>}
     */
    private function sampleData(): array
    {
        return [
            'id' => 28823,
            'assets' => [
                [
                    'key' => 'icon',
                    'value' => 'https://render.worldofwarcraft.com/eu/icons/56/inv_sword_2h_azinothglaive_d_01.jpg',
                    'file_data_id' => 132397,
                ],
            ],
        ];
    }

    #[Test]
    public function from_array_creates_instance_with_correct_properties(): void
    {
        $cast = ItemMediaCast::fromArray($this->sampleData());

        $this->assertSame(28823, $cast->id);
        $this->assertCount(1, $cast->assets);
        $this->assertSame('icon', $cast->assets[0]['key']);
        $this->assertSame(132397, $cast->assets[0]['file_data_id']);
    }

    #[Test]
    public function url_returns_first_asset_value(): void
    {
        $cast = ItemMediaCast::fromArray($this->sampleData());

        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/icons/56/inv_sword_2h_azinothglaive_d_01.jpg',
            $cast->url(),
        );
    }

    #[Test]
    public function url_returns_null_when_assets_are_empty(): void
    {
        $cast = ItemMediaCast::fromArray(['id' => 1, 'assets' => []]);

        $this->assertNull($cast->url());
    }

    #[Test]
    public function to_array_returns_correct_structure(): void
    {
        $data = $this->sampleData();
        $cast = ItemMediaCast::fromArray($data);

        $this->assertSame($data, $cast->toArray());
    }

    #[Test]
    public function get_returns_null_when_value_is_null(): void
    {
        $cast = new ItemMediaCast(id: 1, assets: []);
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'icon', null, []);

        $this->assertNull($result);
    }

    #[Test]
    public function get_casts_json_string_to_item_media_cast(): void
    {
        $cast = new ItemMediaCast(id: 1, assets: []);
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'icon', json_encode($this->sampleData()), []);

        $this->assertInstanceOf(ItemMediaCast::class, $result);
        $this->assertSame(28823, $result->id);
    }

    #[Test]
    public function set_returns_null_when_value_is_null(): void
    {
        $cast = new ItemMediaCast(id: 1, assets: []);
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'icon', null, []);

        $this->assertNull($result);
    }

    #[Test]
    public function set_encodes_item_media_cast_to_json(): void
    {
        $cast = new ItemMediaCast(id: 1, assets: []);
        $model = $this->createStub(Model::class);
        $value = ItemMediaCast::fromArray($this->sampleData());

        $result = $cast->set($model, 'icon', $value, []);

        $this->assertSame(json_encode($this->sampleData()), $result);
    }

    #[Test]
    public function set_encodes_plain_array_to_json(): void
    {
        $cast = new ItemMediaCast(id: 1, assets: []);
        $model = $this->createStub(Model::class);
        $data = $this->sampleData();

        $result = $cast->set($model, 'icon', $data, []);

        $this->assertSame(json_encode($data), $result);
    }
}
