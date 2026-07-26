<?php

namespace Tests\Unit\Casts;

use App\Casts\AsKeyType;
use App\Models\Item;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
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
    public function get_returns_integer_when_commentable_type_has_integer_key_type(): void
    {
        $integerKeyedModel = new class extends Model
        {
            protected $keyType = 'integer';
        };

        $cast = new AsKeyType;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'commentable_id', '42', [
            'commentable_type' => $integerKeyedModel::class,
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

    #[Test]
    public function get_returns_string_fallback_when_commentable_type_is_not_a_model(): void
    {
        $cast = new AsKeyType;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'commentable_id', '99', [
            'commentable_type' => stdClass::class,
        ]);

        $this->assertSame('99', $result);
    }

    #[Test]
    public function get_does_not_instantiate_a_class_requiring_constructor_arguments(): void
    {
        $cast = new AsKeyType;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'commentable_id', '99', [
            'commentable_type' => ClassRequiringConstructorArgs::class,
        ]);

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

    #[Group('error-handling')]
    #[Test]
    public function set_throws_when_value_is_not_numeric_for_an_integer_keyed_model(): void
    {
        $cast = new AsKeyType;
        $model = $this->createStub(Model::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value [abc] is not a valid key for an integer-keyed model.');

        $cast->set($model, 'commentable_id', 'abc', [
            'commentable_type' => Item::class,
        ]);
    }

    #[Test]
    public function set_allows_non_numeric_value_for_a_string_keyed_model(): void
    {
        $stringKeyedModel = new class extends Model
        {
            protected $keyType = 'string';
        };

        $cast = new AsKeyType;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'commentable_id', 'abc-123', [
            'commentable_type' => $stringKeyedModel::class,
        ]);

        $this->assertSame('abc-123', $result);
    }
}

class ClassRequiringConstructorArgs
{
    public function __construct(public string $required) {}
}
