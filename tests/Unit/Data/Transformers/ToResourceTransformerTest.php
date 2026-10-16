<?php

namespace Tests\Unit\Data\Transformers;

use App\Data\Transformers\ToResourceTransformer;
use App\Http\Resources\CharacterSummaryResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Tests\TestCase;

#[Group('platform')]
class ToResourceTransformerTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_returns_the_resolved_array_of_the_models_resource(): void
    {
        $model = $this->mockModelReturningResource(new JsonResource(['id' => 42, 'name' => 'Thrall']));

        $transformed = $this->transform($model);

        $this->assertSame(['id' => 42, 'name' => 'Thrall'], $transformed);
    }

    #[Test]
    #[Group('resource')]
    public function it_omits_conditional_attributes_that_are_not_met(): void
    {
        $resource = new class(null) extends JsonResource
        {
            /**
             * @return array<string, mixed>
             */
            public function toArray($request): array
            {
                return [
                    'id' => 42,
                    'secret' => $this->when(false, 'hidden'),
                    'visible' => $this->when(true, 'shown'),
                ];
            }
        };

        $transformed = $this->transform($this->mockModelReturningResource($resource));

        $this->assertSame(['id' => 42, 'visible' => 'shown'], $transformed);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_resolves_the_given_resource_class(): void
    {
        $model = $this->mockModelReturningResource(new JsonResource(['id' => 42]), CharacterSummaryResource::class);

        $transformed = $this->transform($model, CharacterSummaryResource::class);

        $this->assertSame(['id' => 42], $transformed);
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
     * @param  class-string<JsonResource>|null  $resourceClass
     * @return array<string, mixed>
     */
    private function transform(Model $model, ?string $resourceClass = null): array
    {
        return (new ToResourceTransformer($resourceClass))->transform(
            Mockery::mock(DataProperty::class),
            $model,
            Mockery::mock(TransformationContext::class),
        );
    }
}
