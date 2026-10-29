<?php

namespace Tests\Unit\Http\Resources\Concerns;

use App\Attributes\Http\Resources\ResourceScope;
use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Resources\ScopedTestResource;
use Tests\TestCase;

#[Group('platform')]
class HasScopesTest extends TestCase
{
    // ==================== field scopes ====================

    #[Test]
    public function it_lists_scopes_from_scope_definitions(): void
    {
        $this->assertSame(
            ['summary' => ['id', 'name'], 'detail' => ['id', 'name', 'secret', 'extra', 'merged']],
            $this->makeResource()->scopes(),
        );
    }

    #[Test]
    public function it_resolves_every_field_when_no_scope_is_set(): void
    {
        $this->assertSame(
            ['id' => 1, 'name' => 'Thrall', 'secret' => 's', 'merged' => 'm'],
            $this->makeResource()->resolve(new Request),
        );
    }

    #[Test]
    public function it_keeps_only_the_scopes_fields(): void
    {
        $this->assertSame(
            ['id' => 1, 'name' => 'Thrall'],
            $this->makeResource()->scope('summary')->resolve(new Request),
        );
    }

    #[Test]
    public function it_keeps_merged_and_conditionally_filtered_fields(): void
    {
        $this->assertSame(
            ['id' => 1, 'name' => 'Thrall', 'secret' => 's', 'merged' => 'm'],
            $this->makeResource()->scope('detail')->resolve(new Request),
        );
    }

    #[Test]
    public function scope_returns_the_same_instance(): void
    {
        $resource = $this->makeResource();

        $this->assertSame($resource, $resource->scope('summary'));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_logs_and_falls_back_to_every_field_for_an_undefined_key(): void
    {
        Log::spy();
        $resource = $this->makeResource();

        $array = $resource->scope('typo')->resolve(new Request);

        $this->assertArrayHasKey('secret', $array);
        Log::shouldHaveReceived('error')->once()->with('Scope [typo] is not defined on ['.$resource::class.'].');
    }

    // ==================== definitions ====================

    #[Test]
    public function it_accepts_a_plain_array_definition(): void
    {
        Log::spy();
        $resource = $this->makePlainArrayResource();

        $array = $resource->scope('listing')->resolve(new Request);

        $this->assertSame(['listing' => ['id']], $resource->scopes());
        $this->assertSame(['id' => 1], $array);
        Log::shouldNotHaveReceived('error');
    }

    // ==================== dot notation ====================

    #[Test]
    public function dotted_fields_keep_only_those_fields_of_a_nested_resource_and_collection(): void
    {
        $resource = $this->makeDottedResource();

        $array = $resource->scope('nested')->resolve(new Request);

        $this->assertSame(['id' => 1, 'child' => ['name' => 'Jaina'], 'children' => [['id' => 3], ['id' => 4]]], $array);
    }

    #[Test]
    public function dotted_fields_keep_only_those_fields_of_nested_arrays_at_any_depth(): void
    {
        $resource = $this->makeDottedResource();

        $array = $resource->scope('arrays')->resolve(new Request);

        $this->assertSame([
            'resolved' => ['name' => 'Jaina'],
            'nested' => ['raid' => ['boss' => ['name' => 'Illidan']]],
            'list' => [['id' => 5], ['id' => 6]],
        ], $array);
    }

    #[Test]
    public function a_field_listed_with_and_without_a_dot_keeps_every_nested_field(): void
    {
        $resource = $this->makeDottedResource();

        $array = json_decode(json_encode($resource->scope('overlap')->resolve(new Request)), true);

        $this->assertSame(['child' => ['id' => 2, 'name' => 'Jaina', 'secret' => 's', 'merged' => 'm']], $array);
    }

    #[Test]
    public function dotted_fields_of_a_missing_value_leave_the_field_out(): void
    {
        $resource = $this->makeDottedResource();

        $array = $resource->scope('missing')->resolve(new Request);

        $this->assertSame(['id' => 1], $array);
    }

    // ==================== attributes and caching ====================

    #[Test]
    public function it_reads_resource_scope_attributes_when_scope_definitions_is_empty(): void
    {
        $resource = $this->makeAttributeResource();

        $this->assertSame(['listing' => ['id']], $resource->scopes());
        $this->assertSame(['id' => 1], $resource->scope('listing')->resolve(new Request));
    }

    #[Test]
    public function scope_definitions_take_priority_over_attributes(): void
    {
        $this->assertSame(['summary' => ['id', 'name']], $this->makeMixedResource()->scopes());
    }

    #[Test]
    public function it_caches_scopes_per_class(): void
    {
        $this->makeAttributeResource()->scopes();

        $this->assertSame(['summary', 'detail'], array_keys($this->makeResource()->scopes()));
    }

    /**
     * @param  array{id: int, name: string, secret: string}  $row
     */
    private function makeResource(array $row = ['id' => 1, 'name' => 'Thrall', 'secret' => 's']): JsonResource&ScopedResource
    {
        return new class($row) extends JsonResource implements ScopedResource
        {
            use HasScopes;

            /**
             * @return array<string|int, mixed>
             */
            public function toArray(Request $request): array
            {
                return $this->scoped([
                    'id' => $this->resource['id'],
                    'name' => $this->resource['name'],
                    'secret' => $this->resource['secret'],
                    'extra' => $this->when(false, 'x'),
                    $this->mergeWhen(true, ['merged' => 'm']),
                ]);
            }

            /**
             * @return array<string, list<string>>
             */
            protected function scopeDefinitions(): array
            {
                return ['summary' => $this->summary(), 'detail' => $this->detailed()];
            }

            /**
             * @return list<string>
             */
            protected function summary(): array
            {
                return ['id', 'name'];
            }

            /**
             * @return list<string>
             */
            protected function detailed(): array
            {
                return ['id', 'name', 'secret', 'extra', 'merged'];
            }
        };
    }

    private function makePlainArrayResource(): JsonResource&ScopedResource
    {
        return new class(['id' => 1, 'name' => 'Thrall']) extends JsonResource implements ScopedResource
        {
            use HasScopes;

            /**
             * @return array<string, mixed>
             */
            public function toArray(Request $request): array
            {
                return $this->scoped(['id' => $this->resource['id'], 'name' => $this->resource['name']]);
            }

            /**
             * @return array<string, list<string>>
             */
            protected function scopeDefinitions(): array
            {
                return ['listing' => ['id']];
            }
        };
    }

    /**
     * A resource with a nested resource, collection, arrays and a missing
     * value, and scopes of dotted fields.
     */
    private function makeDottedResource(): JsonResource&ScopedResource
    {
        return new class([]) extends JsonResource implements ScopedResource
        {
            use HasScopes;

            /**
             * @return array<string, mixed>
             */
            public function toArray(Request $request): array
            {
                return $this->scoped([
                    'id' => 1,
                    'child' => new ScopedTestResource(['id' => 2, 'name' => 'Jaina', 'secret' => 's']),
                    'children' => ScopedTestResource::collection([
                        ['id' => 3, 'name' => 'Sylvanas', 'secret' => 's'],
                        ['id' => 4, 'name' => 'Anduin', 'secret' => 's'],
                    ]),
                    'resolved' => (new ScopedTestResource(['id' => 2, 'name' => 'Jaina', 'secret' => 's']))->resolve($request),
                    'nested' => ['raid' => ['id' => 7, 'boss' => ['id' => 8, 'name' => 'Illidan']]],
                    'list' => [['id' => 5, 'name' => 'Varian'], ['id' => 6, 'name' => 'Tyrande']],
                    'missing' => $this->when(false, ['name' => 'x']),
                ]);
            }

            /**
             * @return array<string, list<string>>
             */
            protected function scopeDefinitions(): array
            {
                return [
                    'nested' => ['id', 'child.name', 'children.id'],
                    'arrays' => ['resolved.name', 'nested.raid.boss.name', 'list.id'],
                    'overlap' => ['child.name', 'child'],
                    'missing' => ['id', 'missing.name'],
                ];
            }
        };
    }

    private function makeAttributeResource(): JsonResource&ScopedResource
    {
        return new #[ResourceScope('listing', fields: ['id'])] class(['id' => 1, 'name' => 'Thrall']) extends JsonResource implements ScopedResource
        {
            use HasScopes;

            /**
             * @return array<string, mixed>
             */
            public function toArray(Request $request): array
            {
                return $this->scoped(['id' => $this->resource['id'], 'name' => $this->resource['name']]);
            }
        };
    }

    private function makeMixedResource(): JsonResource&ScopedResource
    {
        return new #[ResourceScope('listing', fields: ['id'])] class(['id' => 1, 'name' => 'Thrall']) extends JsonResource implements ScopedResource
        {
            use HasScopes;

            /**
             * @return array<string, list<string>>
             */
            protected function scopeDefinitions(): array
            {
                return ['summary' => $this->summary()];
            }

            /**
             * @return list<string>
             */
            protected function summary(): array
            {
                return ['id', 'name'];
            }
        };
    }
}
