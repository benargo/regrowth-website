<?php

namespace App\Http\Resources\Concerns;

use App\Attributes\Http\Resources\ResourceScope;
use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\ScopedResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use ReflectionAttribute;
use ReflectionClass;

/**
 * Lets a resource limit its output to a named scope's fields.
 *
 * Scopes come from `scopeDefinitions()` or, when that is empty,
 * `#[ResourceScope]` class attributes. Each scope is a list of field keys;
 * a dot-notation key such as `bosses.name` keeps only that field of a
 * nested resource, collection or array. Definitions are read once per class
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
     * @var array<class-string, array<string, array<string, true|array<string, mixed>>>>
     */
    private static array $fieldTrees = [];

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

        $fieldTree = self::$fieldTrees[static::class][$scope] ??= static::fieldTree(static::definedScopes()[$scope]);
        $scopedData = array_intersect_key($expanded, $fieldTree);

        foreach ($scopedData as $key => $value) {
            if ($value instanceof ScopedResource) {
                $this->applyScopeMapping($value, $scope);
            }

            if (is_array($fieldTree[$key])) {
                $scopedData[$key] = static::onlyFields($value, $fieldTree[$key]);
            }
        }

        return $scopedData;
    }

    /**
     * Turn a list of dot-notation fields into a tree keyed by field name. A
     * field listed without nested fields keeps everything beneath it.
     *
     * @param  list<string>  $fields
     * @return array<string, true|array<string, mixed>>
     */
    private static function fieldTree(array $fields): array
    {
        $nestedFields = [];

        foreach ($fields as $field) {
            [$key, $rest] = array_pad(explode('.', $field, 2), 2, null);

            if ($rest === null) {
                $nestedFields[$key] = true;

                continue;
            }

            if (($nestedFields[$key] ?? null) !== true) {
                $nestedFields[$key][] = $rest;
            }
        }

        return array_map(
            fn (true|array $nested): true|array => $nested === true ? true : static::fieldTree($nested),
            $nestedFields,
        );
    }

    /**
     * Keep only the fields of the given tree in a nested value, resolving a
     * nested resource and applying the tree to each item of a list or
     * collection. Missing and scalar values are returned unchanged.
     *
     * @param  array<string, true|array<string, mixed>>  $fieldTree
     */
    private static function onlyFields(mixed $value, array $fieldTree): mixed
    {
        if ($value instanceof ResourceCollection) {
            return $value->collection
                ->map(fn (mixed $item): mixed => static::onlyFields($item, $fieldTree))
                ->all();
        }

        if ($value instanceof JsonResource) {
            if ($value->resource instanceof MissingValue) {
                return $value;
            }

            $value = $value->resource === null ? null : $value->resolve();
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => static::onlyFields($item, $fieldTree), $value);
        }

        $filtered = array_intersect_key($value, $fieldTree);

        foreach ($filtered as $key => $nestedValue) {
            if (is_array($fieldTree[$key])) {
                $filtered[$key] = static::onlyFields($nestedValue, $fieldTree[$key]);
            }
        }

        return $filtered;
    }

    /**
     * Apply the active scope's mapping for the given nested resource's class,
     * for a nested resource that is resolved inside `toArray()`.
     */
    protected function scopeNested(ScopedResource $resource): ScopedResource
    {
        $scope = $this->activeScope();

        if ($scope !== null) {
            $this->applyScopeMapping($resource, $scope);
        }

        return $resource;
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
     * Apply the given scope's mapping for the nested resource's class, matching a
     * `ScopedResourceCollection` by the class it collects.
     */
    private function applyScopeMapping(ScopedResource $resource, string $scope): void
    {
        $nestedClass = $resource instanceof ScopedResourceCollection ? $resource->collects : $resource::class;
        $mappings = static::definedScopeMappings()[$scope] ?? [];

        if (! array_key_exists($nestedClass, $mappings)) {
            return;
        }

        $resource->scope($mappings[$nestedClass]);
    }

    /**
     * Read the scopes from `scopeDefinitions()`, or from `#[ResourceScope]`
     * attributes when it is empty.
     *
     * @return array<string, list<string>>
     */
    private static function resolveScopes(): array
    {
        $class = new ReflectionClass(static::class);
        $definitions = $class->newInstanceWithoutConstructor()->scopeDefinitions();

        if ($definitions !== []) {
            return $definitions;
        }

        return static::resourceScopeAttributes($class)
            ->mapWithKeys(fn (ResourceScope $scope): array => [$scope->name => $scope->fields])
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
