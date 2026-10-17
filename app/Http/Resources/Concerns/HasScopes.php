<?php

namespace App\Http\Resources\Concerns;

use App\Attributes\Http\Resources\ResourceScope;
use App\Attributes\Http\Resources\ScopeDefinition;
use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\ScopedResourceCollection;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * Lets a resource limit its output to a named scope's fields.
 *
 * Scopes come from `scopeDefinitions()` or, when that is empty,
 * `#[ResourceScope]` class attributes. Definitions are read once per class
 * without calling the constructor, so they must not depend on the wrapped
 * resource.
 */
trait HasScopes
{
    protected ?string $scope = null;

    /**
     * @var array<class-string, array<string, list<string>>>
     */
    private static array $definedScopes = [];

    /**
     * @var array<class-string, array<string, array<class-string, string>>>
     */
    private static array $definedScopeMappings = [];

    /**
     * @var array<class-string, string>
     */
    private static array $requestScopes = [];

    /**
     * The scopes of this resource, keyed by name.
     *
     * @return array<string, list<string>>
     */
    protected function scopeDefinitions(): array
    {
        return [];
    }

    /**
     * The scope applied to each nested resource class, keyed by this resource's scope.
     *
     * @return array<string, array<class-string, string>>
     */
    protected function scopeMappings(): array
    {
        return [];
    }

    /**
     * The validated scopes of this resource class, keyed by name.
     *
     * @return array<string, list<string>>
     */
    public static function definedScopes(): array
    {
        return self::$definedScopes[static::class] ??= static::resolveScopes();
    }

    /**
     * The validated scope mappings of this resource class.
     *
     * @return array<string, array<class-string, string>>
     */
    public static function definedScopeMappings(): array
    {
        return self::$definedScopeMappings[static::class] ??= static::resolveScopeMappings();
    }

    /**
     * @return array<string, list<string>>
     */
    public function scopes(): array
    {
        return static::definedScopes();
    }

    /**
     * Limit the output to the fields of the given scope. An undefined scope
     * is logged and ignored.
     */
    public function scope(string $key): static
    {
        if (static::isDefinedScope($key)) {
            $this->scope = $key;
        }

        return $this;
    }

    /**
     * Apply the given scope to every instance of this exact class until it
     * is cleared. An undefined scope is logged and ignored.
     */
    public static function setRequestScope(string $key): void
    {
        if (static::isDefinedScope($key)) {
            self::$requestScopes[static::class] = $key;
        }
    }

    /**
     * Remove the scope set by `setRequestScope()` for this exact class.
     */
    public static function clearRequestScope(): void
    {
        unset(self::$requestScopes[static::class]);
    }

    /**
     * The scope that applies to this resource: its own, then the request
     * scope for its exact class.
     */
    protected function activeScope(): ?string
    {
        return $this->scope ?? self::$requestScopes[static::class] ?? null;
    }

    /**
     * Keep only the active scope's fields, expanding top-level merged values,
     * and scope the nested resources that remain.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected function scoped(array $data): array
    {
        $scope = $this->activeScope();

        if ($scope === null) {
            return $data;
        }

        $expanded = [];

        foreach ($data as $key => $value) {
            if (is_int($key) && $value instanceof MergeValue) {
                foreach ($value->data as $mergedKey => $mergedValue) {
                    $expanded[$mergedKey] = $mergedValue;
                }

                continue;
            }

            $expanded[$key] = $value;
        }

        $scopedData = array_intersect_key($expanded, array_flip(static::definedScopes()[$scope]));
        $mappings = static::definedScopeMappings()[$scope] ?? [];

        foreach ($scopedData as $value) {
            if (! $value instanceof ScopedResource) {
                continue;
            }

            $nestedClass = $value instanceof ScopedResourceCollection ? $value->collects : $value::class;

            if (array_key_exists($nestedClass, $mappings)) {
                $value->scope($mappings[$nestedClass]);
            }
        }

        return $scopedData;
    }

    /**
     * Create a collection of this resource that can be scoped.
     *
     * @param  mixed  $resource
     */
    protected static function newCollection($resource): ScopedResourceCollection
    {
        return new ScopedResourceCollection($resource, static::class);
    }

    /**
     * Whether the given scope is defined on this class, logging when it is not.
     */
    private static function isDefinedScope(string $key): bool
    {
        if (array_key_exists($key, static::definedScopes())) {
            return true;
        }

        Log::error("Scope [{$key}] is not defined on [".static::class.'].');

        return false;
    }

    /**
     * Read the scopes, dropping `scopeDefinitions()` entries without a
     * `#[ScopeDefinition]` method.
     *
     * @return array<string, list<string>>
     */
    private static function resolveScopes(): array
    {
        $class = new ReflectionClass(static::class);
        $definitions = $class->newInstanceWithoutConstructor()->scopeDefinitions();

        if ($definitions === []) {
            return static::resourceScopeAttributes($class)
                ->mapWithKeys(fn (ResourceScope $scope): array => [$scope->name => $scope->fields])
                ->all();
        }

        $backedKeys = collect($class->getMethods())
            ->flatMap(fn (ReflectionMethod $method) => collect($method->getAttributes(ScopeDefinition::class))
                ->map(fn (ReflectionAttribute $attribute): string => $attribute->newInstance()->key ?? $method->getName()))
            ->flip();

        return collect($definitions)
            ->filter(function (array $fields, string $key) use ($backedKeys): bool {
                if ($backedKeys->has($key)) {
                    return true;
                }

                Log::error("Scope [{$key}] on [".static::class.'] is not backed by a #[ScopeDefinition] method.');

                return false;
            })
            ->all();
    }

    /**
     * Read the scope mappings, dropping each mapping to a scope its nested
     * resource class does not define.
     *
     * @return array<string, array<class-string, string>>
     */
    private static function resolveScopeMappings(): array
    {
        $class = new ReflectionClass(static::class);
        $mappings = $class->newInstanceWithoutConstructor()->scopeMappings();

        if ($mappings === []) {
            $mappings = static::resourceScopeAttributes($class)
                ->mapWithKeys(fn (ResourceScope $scope): array => [$scope->name => $scope->mappings])
                ->all();
        }

        return collect($mappings)
            ->map(fn (array $scopeMappings): array => collect($scopeMappings)
                ->filter(function (string $nestedScope, string $nestedClass): bool {
                    if (method_exists($nestedClass, 'definedScopes') && array_key_exists($nestedScope, $nestedClass::definedScopes())) {
                        return true;
                    }

                    Log::error("Scope [{$nestedScope}] is not defined on [{$nestedClass}].");

                    return false;
                })
                ->all())
            ->all();
    }

    /**
     * @param  ReflectionClass<static>  $class
     * @return Collection<int, ResourceScope>
     */
    private static function resourceScopeAttributes(ReflectionClass $class): Collection
    {
        return collect($class->getAttributes(ResourceScope::class))
            ->map(fn (ReflectionAttribute $attribute): ResourceScope => $attribute->newInstance());
    }
}
