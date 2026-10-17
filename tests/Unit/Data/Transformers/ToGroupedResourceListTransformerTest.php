<?php

namespace Tests\Unit\Data\Transformers;

use App\Data\Transformers\ToGroupedResourceListTransformer;
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
class ToGroupedResourceListTransformerTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_transforms_each_group_keeping_the_group_keys(): void
    {
        $transformed = $this->transform(collect([
            '>=80' => collect([$this->mockModelReturningResource(new JsonResource(['id' => 1]))]),
            '<50' => collect([null, $this->mockModelReturningResource(new JsonResource(['id' => 2]))]),
        ]));

        $this->assertSame(['>=80' => [['id' => 1]], '<50' => [1 => ['id' => 2]]], $transformed);
    }

    #[Test]
    #[Group('resource')]
    public function it_passes_the_given_resource_class_and_scope_to_each_group(): void
    {
        $model = $this->mockModelReturningResource(new ScopedTestResource(['id' => 2, 'name' => 'Jaina', 'secret' => 's']), CharacterSummaryResource::class);

        $transformed = $this->transform(['group' => [$model]], CharacterSummaryResource::class, 'summary');

        $this->assertSame(['group' => [['id' => 2, 'name' => 'Jaina']]], $transformed);
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
     * @param  iterable<array-key, iterable<int, Model|null>>  $groups
     * @param  class-string<JsonResource>|null  $resourceClass
     * @return array<array-key, array<int, array<string, mixed>>>
     */
    private function transform(iterable $groups, ?string $resourceClass = null, ?string $scope = null): array
    {
        return (new ToGroupedResourceListTransformer($resourceClass, $scope))->transform(
            Mockery::mock(DataProperty::class),
            $groups,
            Mockery::mock(TransformationContext::class),
        );
    }
}
