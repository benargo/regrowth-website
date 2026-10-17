<?php

namespace Tests\Unit\Http\Resources\Concerns;

use App\Attributes\Http\Resources\ResourceScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Resources\ParentScopedTestResource;
use Tests\Support\Resources\ScopedTestResource;
use Tests\TestCase;

#[Group('platform')]
class HasScopeMappingsTest extends TestCase
{
    #[Test]
    public function it_scopes_mapped_nested_resources_and_collections(): void
    {
        $array = $this->resolveDeep((new ParentScopedTestResource($this->row()))->scope('listing'));

        $this->assertSame(['id' => 2, 'name' => 'Jaina'], $array['child']);
        $this->assertSame([['id' => 3, 'name' => 'Sylvanas'], ['id' => 4, 'name' => 'Anduin']], $array['children']);
    }

    #[Test]
    public function it_leaves_nested_resources_unscoped_when_the_scope_has_no_mapping(): void
    {
        $array = $this->resolveDeep((new ParentScopedTestResource($this->row()))->scope('full'));

        $this->assertArrayHasKey('secret', $array['child']);
        $this->assertArrayHasKey('secret', $array['children'][0]);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_logs_a_mapping_to_an_undefined_scope_and_keeps_every_field(): void
    {
        Log::spy();
        $resource = new class($this->row()) extends ParentScopedTestResource
        {
            /**
             * @return array<string, array<class-string, string>>
             */
            protected function scopeMappings(): array
            {
                return ['listing' => [ScopedTestResource::class => 'typo']];
            }
        };

        $array = $this->resolveDeep($resource->scope('listing'));

        $this->assertArrayHasKey('secret', $array['child']);
        Log::shouldHaveReceived('error')->with('Scope [typo] is not defined on ['.ScopedTestResource::class.'].');
    }

    #[Test]
    #[Group('error-handling')]
    public function it_logs_a_mapping_to_an_undefined_scope_once_for_a_collection_of_parents(): void
    {
        Log::spy();
        $parent = new class([]) extends ParentScopedTestResource
        {
            /**
             * @return array<string, array<class-string, string>>
             */
            protected function scopeMappings(): array
            {
                return ['listing' => [ScopedTestResource::class => 'misspelt']];
            }
        };

        $parent::collection([$this->row(), $this->row()])->scope('listing')->resolve();

        Log::shouldHaveReceived('error')->once()->with('Scope [misspelt] is not defined on ['.ScopedTestResource::class.'].');
    }

    #[Test]
    public function it_reads_mappings_from_resource_scope_attributes(): void
    {
        $resource = new #[ResourceScope('listing', fields: ['id', 'child'], mappings: [ScopedTestResource::class => 'summary'])] class($this->row()) extends ParentScopedTestResource
        {
            /**
             * @return array<string, list<string>>
             */
            protected function scopeDefinitions(): array
            {
                return [];
            }

            /**
             * @return array<string, array<class-string, string>>
             */
            protected function scopeMappings(): array
            {
                return [];
            }
        };

        $array = $this->resolveDeep($resource->scope('listing'));

        $this->assertSame(['id' => 1, 'child' => ['id' => 2, 'name' => 'Jaina']], $array);
    }

    #[Test]
    public function scope_mappings_take_priority_over_attribute_mappings(): void
    {
        $resource = new #[ResourceScope('listing', mappings: [ScopedTestResource::class => 'detail'])] class($this->row()) extends ParentScopedTestResource {};

        $array = $this->resolveDeep($resource->scope('listing'));

        $this->assertSame(['id' => 2, 'name' => 'Jaina'], $array['child']);
    }

    #[Test]
    public function it_scopes_a_nested_resource_resolved_inside_to_array(): void
    {
        $array = $this->parentResolvingItsChild()->scope('listing')->resolve(new Request);

        $this->assertSame(['id' => 2, 'name' => 'Jaina'], $array['child']);
    }

    #[Test]
    public function it_leaves_a_nested_resource_resolved_inside_to_array_unscoped_without_a_mapping(): void
    {
        $array = $this->parentResolvingItsChild()->scope('full')->resolve(new Request);

        $this->assertArrayHasKey('secret', $array['child']);
    }

    #[Test]
    public function it_leaves_a_nested_resource_resolved_inside_to_array_unscoped_without_a_scope(): void
    {
        $array = $this->parentResolvingItsChild()->resolve(new Request);

        $this->assertArrayHasKey('secret', $array['child']);
    }

    /**
     * @return array{id: int, child: array<string, mixed>, children: list<array<string, mixed>>}
     */
    private function row(): array
    {
        return [
            'id' => 1,
            'child' => ['id' => 2, 'name' => 'Jaina', 'secret' => 's'],
            'children' => [
                ['id' => 3, 'name' => 'Sylvanas', 'secret' => 's'],
                ['id' => 4, 'name' => 'Anduin', 'secret' => 's'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDeep(JsonResource $resource): array
    {
        return json_decode(json_encode($resource->resolve()), true);
    }

    /**
     * A parent resource that resolves its child to an array inside `toArray()`.
     */
    private function parentResolvingItsChild(): ParentScopedTestResource
    {
        return new class($this->row()) extends ParentScopedTestResource
        {
            /**
             * @return array<string, mixed>
             */
            public function toArray(Request $request): array
            {
                return $this->scoped([
                    'id' => $this->resource['id'],
                    'child' => $this->scopeNested(new ScopedTestResource($this->resource['child']))->resolve($request),
                ]);
            }
        };
    }
}
