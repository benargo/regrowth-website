<?php

namespace Tests\Unit\Casts;

use App\Casts\AsKeyType;
use App\Models\Item;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class AsKeyTypeTest extends TestCase
{
    // ==================== get ====================

    #[Test]
    public function get_returns_integer_when_commentable_type_is_integer_keyed_model(): void
    {
        $cast = new AsKeyType;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'commentable_id', '42', [
            'commentable_type' => Item::class,
        ]);

        $this->assertSame(42, $result);
    }

    #[Test]
    public function get_returns_string_when_commentable_type_is_string_keyed_model(): void
    {
        $stringKeyedModel = new class extends Model
        {
            protected $keyType = 'string';
        };

        $cast = new AsKeyType;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'commentable_id', 'abc-123', [
            'commentable_type' => $stringKeyedModel::class,
        ]);

        $this->assertSame('abc-123', $result);
    }

    #[Test]
    public function get_returns_null_when_value_is_null(): void
    {
        $cast = new AsKeyType;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'commentable_id', null, [
            'commentable_type' => Item::class,
        ]);

        $this->assertNull($result);
    }

    #[Test]
    public function get_returns_string_fallback_when_commentable_type_is_null(): void
    {
        $cast = new AsKeyType;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'commentable_id', '99', []);

        $this->assertSame('99', $result);
    }

    // ==================== set ====================

    #[Test]
    public function set_returns_string_representation_of_integer_value(): void
    {
        $cast = new AsKeyType;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'commentable_id', 42, [
            'commentable_type' => Item::class,
        ]);

        $this->assertSame('42', $result);
    }

    #[Test]
    public function set_returns_string_as_is_for_string_values(): void
    {
        $cast = new AsKeyType;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'commentable_id', 'abc-123', []);

        $this->assertSame('abc-123', $result);
    }

    #[Test]
    public function set_returns_null_when_value_is_null(): void
    {
        $cast = new AsKeyType;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'commentable_id', null, [
            'commentable_type' => Item::class,
        ]);

        $this->assertNull($result);
    }

    #[Test]
    public function set_handles_null_commentable_type_gracefully(): void
    {
        $cast = new AsKeyType;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'commentable_id', '77', []);

        $this->assertSame('77', $result);
    }
}
