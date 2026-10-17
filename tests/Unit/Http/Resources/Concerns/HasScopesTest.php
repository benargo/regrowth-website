<?php

namespace Tests\Unit\Http\Resources\Concerns;

use App\Attributes\Http\Resources\ResourceScope;
use App\Attributes\Http\Resources\ScopeDefinition;
use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class HasScopesTest extends TestCase
{
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
    public function it_uses_the_attribute_key_and_keeps_merged_and_conditional_filtering(): void
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

    #[Test]
    #[Group('error-handling')]
    public function it_logs_and_drops_a_definition_without_a_scope_definition_method(): void
    {
        Log::spy();
        $resource = $this->makeOrphanResource();

        $this->assertSame([], $resource->scopes());
        Log::shouldHaveReceived('error')->once()->with('Scope [orphan] on ['.$resource::class.'] is not backed by a #[ScopeDefinition] method.');
    }

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
            #[ScopeDefinition]
            protected function summary(): array
            {
                return ['id', 'name'];
            }

            /**
             * @return list<string>
             */
            #[ScopeDefinition('detail')]
            protected function detailed(): array
            {
                return ['id', 'name', 'secret', 'extra', 'merged'];
            }
        };
    }

    private function makeOrphanResource(): JsonResource&ScopedResource
    {
        return new class(['id' => 1]) extends JsonResource implements ScopedResource
        {
            use HasScopes;

            /**
             * @return array<string, list<string>>
             */
            protected function scopeDefinitions(): array
            {
                return ['orphan' => ['id']];
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
            #[ScopeDefinition]
            protected function summary(): array
            {
                return ['id', 'name'];
            }
        };
    }
}
