<?php

namespace Tests\Support;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

abstract class ModelTestCase extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** ---------- Factory helpers ---------- */
    protected function make(array $overrides = []): Model
    {
        return $this->factory()->make($overrides);
    }

    protected function create(array $overrides = []): Model
    {
        return $this->factory()->create($overrides);
    }

    protected function factory(): Factory
    {
        /** @var class-string<Model> $class */
        $class = $this->modelClass();

        return Factory::factoryForModel($class);
    }

    /** ---------- Schema / attribute helpers ---------- */

    /**
     * Assert that a model's $casts includes (at least) the expected key=>type pairs.
     */
    protected function assertCasts(Model $model, array $expected): void
    {
        $casts = $model->getCasts();
        foreach ($expected as $key => $type) {
            $this->assertArrayHasKey($key, $casts, "Missing cast for [$key]");
            $this->assertSame($type, $casts[$key], "Cast mismatch for [$key]");
        }
    }

    protected function assertFillable(Model $model, array $expected): void
    {
        $this->assertEqualsCanonicalizing($expected, $model->getFillable(), 'Unexpected $fillable.');
    }

    protected function assertHidden(Model $model, array $expected): void
    {
        $this->assertEqualsCanonicalizing($expected, $model->getHidden(), 'Unexpected $hidden.');
    }

    /** ---------- Database helpers ---------- */
    protected function assertTableHas(array $data): void
    {
        $this->assertDatabaseHas($this->tableName(), $data);
    }

    protected function assertTableMissing(array $data): void
    {
        $this->assertDatabaseMissing($this->tableName(), $data);
    }

    protected function tableName(): string
    {
        /** @var Model $m */
        $m = new ($this->modelClass());

        return $m->getTable();
    }

    /**
     * Assert a uniqueness constraint by attempting to persist two records with same unique fields.
     * Pass a closure that creates the second record.
     */
    protected function assertUniqueConstraint(\Closure $persistSecondRecord): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $persistSecondRecord();
    }

    /** ---------- Relation helpers ---------- */

    /**
     * Sanity check that a relation method exists and can load.
     */
    protected function assertRelation(Model $model, string $relationName, string $relationClass): void
    {
        /** @var Relation $relation */
        $relation = $model->{$relationName}();
        $this->assertInstanceOf($relationClass, $relation, "Relation [$relationName] is not a $relationClass");

        // Touch the relation to ensure it loads without error
        $loaded = $model->{$relationName};
        $this->assertNotNull($loaded, "Relation [$relationName] failed to load");
    }
}
