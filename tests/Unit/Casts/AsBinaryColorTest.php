<?php

namespace Tests\Unit\Casts;

use App\Casts\AsBinaryColor;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class AsBinaryColorTest extends TestCase
{
    // ==================== get ====================

    #[Test]
    public function get_returns_null_when_value_is_null(): void
    {
        $cast = new AsBinaryColor;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'color', null, []);

        $this->assertNull($result);
    }

    #[Test]
    public function get_returns_hex_string_from_binary_value(): void
    {
        $cast = new AsBinaryColor;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'color', hex2bin('8b7ed0'), []);

        $this->assertSame('8b7ed0', $result);
    }

    // ==================== set ====================

    #[Test]
    public function set_returns_null_when_value_is_null(): void
    {
        $cast = new AsBinaryColor;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'color', null, []);

        $this->assertNull($result);
    }

    #[Test]
    public function set_accepts_hex_string_without_hash(): void
    {
        $cast = new AsBinaryColor;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'color', '8b7ed0', []);

        $this->assertSame(hex2bin('8b7ed0'), $result);
    }

    #[Test]
    public function set_accepts_hex_string_with_hash(): void
    {
        $cast = new AsBinaryColor;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'color', '#8b7ed0', []);

        $this->assertSame(hex2bin('8b7ed0'), $result);
    }

    #[Test]
    public function set_accepts_integer_hex_literal(): void
    {
        $cast = new AsBinaryColor;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'color', 0x8B7ED0, []);

        $this->assertSame(hex2bin('8b7ed0'), $result);
    }

    #[Test]
    public function set_accepts_rgb_string(): void
    {
        $cast = new AsBinaryColor;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'color', '34,110,115', []);

        $this->assertSame(hex2bin('226e73'), $result);
    }

    #[Test]
    public function set_accepts_rgb_string_with_spaces(): void
    {
        $cast = new AsBinaryColor;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'color', '34, 110, 115', []);

        $this->assertSame(hex2bin('226e73'), $result);
    }

    #[Test]
    public function set_accepts_rgba_string_and_discards_alpha(): void
    {
        $cast = new AsBinaryColor;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'color', '34,110,115,255', []);

        $this->assertSame(hex2bin('226e73'), $result);
    }

    #[Test]
    public function set_accepts_rgb_array(): void
    {
        $cast = new AsBinaryColor;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'color', [34, 110, 115], []);

        $this->assertSame(hex2bin('226e73'), $result);
    }

    #[Test]
    public function set_accepts_rgba_array_and_discards_alpha(): void
    {
        $cast = new AsBinaryColor;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'color', [34, 110, 115, 255], []);

        $this->assertSame(hex2bin('226e73'), $result);
    }

    #[Group('error-handling')]
    #[Test]
    public function set_throws_for_invalid_string(): void
    {
        $cast = new AsBinaryColor;
        $model = $this->createStub(Model::class);

        $this->expectException(InvalidArgumentException::class);

        $cast->set($model, 'color', 'not-a-color', []);
    }

    #[Group('error-handling')]
    #[Test]
    public function set_throws_for_rgb_string_with_out_of_range_values(): void
    {
        $cast = new AsBinaryColor;
        $model = $this->createStub(Model::class);

        $this->expectException(InvalidArgumentException::class);

        $cast->set($model, 'color', '256,0,0', []);
    }
}
