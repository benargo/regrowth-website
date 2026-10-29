<?php

namespace Tests\Unit\Data\Transformers;

use App\Data\Transformers\ToResourceListTransformer;
use App\Http\Resources\CharacterSummaryResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Tests\Support\Resources\ScopedTestResource;
use Tests\TestCase;

#[Group('platform')]
class ToResourceListTransformerTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_drops_the_nulls_and_keeps_the_original_indexes(): void
    {
        $first = $this->mockModelReturningResource(new JsonResource(['id' => 1]));
        $third = $this->mockModelReturningResource(new JsonResource(['id' => 3]));

        $transformed = $this->transform([null, $first, null, $third]);

        $this->assertSame([1 => ['id' => 1], 3 => ['id' => 3]], $transformed);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_resolves_the_given_resource_class(): void
    {
        $model = $this->mockModelReturningResource(new JsonResource(['id' => 42]), CharacterSummaryResource::class);

        $transformed = $this->transform([$model], CharacterSummaryResource::class);

        $this->assertSame([['id' => 42]], $transformed);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_resolves_each_models_default_resource_when_none_is_given(): void
    {
        $model = $this->mockModelReturningResource(new JsonResource(['id' => 42, 'name' => 'Thrall']));

        $transformed = $this->transform([$model]);

        $this->assertSame([['id' => 42, 'name' => 'Thrall']], $transformed);
    }

    #[Test]
    #[Group('resource')]
    public function it_applies_the_given_scope_to_each_resource_before_resolving(): void
    {
        $model = $this->mockModelReturningResource(new ScopedTestResource(['id' => 2, 'name' => 'Jaina', 'secret' => 's']));

        $transformed = $this->transform([$model], scope: 'summary');

        $this->assertSame([['id' => 2, 'name' => 'Jaina']], $transformed);
    }

    #[Test]
    #[Group('resource')]
    public function it_ignores_the_scope_for_a_resource_that_is_not_scoped(): void
    {
        $model = $this->mockModelReturningResource(new JsonResource(['id' => 2, 'name' => 'Jaina']));

        $transformed = $this->transform([$model], scope: 'summary');

        $this->assertSame([['id' => 2, 'name' => 'Jaina']], $transformed);
    }

    #[Test]
    public function it_returns_an_empty_array_for_a_list_of_nulls(): void
    {
        $this->assertSame([], $this->transform([null, null]));
    }

    // ==================== helpers ====================

    /**
     * @param  class-string<JsonResource>|null  $resourceClass
     */
    private function mockModelReturningResource(JsonResource $resource, ?string $resourceClass = null): Model
    {
        $model = Mockery::mock(Model::class);
        $model->shouldReceive('toResource')->once()->with($resourceClass)->andReturn($resource);

        return $model;
    }

    /**
     * @param  array<int, Model|null>  $models
     * @param  class-string<JsonResource>|null  $resourceClass
     * @return array<int, array<string, mixed>>
     */
    private function transform(array $models, ?string $resourceClass = null, ?string $scope = null): array
    {
        return (new ToResourceListTransformer($resourceClass, $scope))->transform(
            Mockery::mock(DataProperty::class),
            $models,
            Mockery::mock(TransformationContext::class),
        );
    }
}
