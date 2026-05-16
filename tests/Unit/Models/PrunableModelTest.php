<?php

namespace Tests\Unit\Models;

use App\Events\ModelPruned;
use App\Models\PrunableModel;
use App\Models\PrunedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Tests\TestCase;

class ConcreteModel extends PrunableModel
{
    use HasUuids;

    protected $table = 'concrete_models';

    public $timestamps = false;

    protected $fillable = ['id'];

    public function prunable(): Builder
    {
        return static::query();
    }
}

class PrunableModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('concrete_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('concrete_models');

        parent::tearDown();
    }

    #[Test]
    public function pruning_dispatches_a_model_pruned_event(): void
    {
        Event::fake();

        $model = ConcreteModel::create(['id' => fake()->uuid()]);

        $model->prune();

        Event::assertDispatched(ModelPruned::class, function (ModelPruned $dispatched) use ($model) {
            return $dispatched->model->is($model);
        });
    }

    #[Test]
    public function resolve_route_binding_returns_the_model_when_found(): void
    {
        $id = fake()->uuid();
        ConcreteModel::create(['id' => $id]);

        $result = (new ConcreteModel)->resolveRouteBinding($id);

        $this->assertInstanceOf(ConcreteModel::class, $result);
        $this->assertSame($id, $result->id);
    }

    #[Test]
    public function resolve_route_binding_throws_gone_exception_for_pruned_id(): void
    {
        $id = fake()->uuid();
        PrunedModel::create(['id' => $id, 'type' => ConcreteModel::class]);

        $this->expectException(GoneHttpException::class);

        (new ConcreteModel)->resolveRouteBinding($id);
    }

    #[Test]
    public function resolve_route_binding_returns_null_for_unknown_id(): void
    {
        $result = (new ConcreteModel)->resolveRouteBinding(fake()->uuid());

        $this->assertNull($result);
    }

    #[Test]
    public function resolve_route_binding_does_not_throw_for_pruned_id_of_different_type(): void
    {
        $id = fake()->uuid();
        PrunedModel::create(['id' => $id, 'type' => 'App\\Models\\SomeOtherModel']);

        $result = (new ConcreteModel)->resolveRouteBinding($id);

        $this->assertNull($result);
    }
}
