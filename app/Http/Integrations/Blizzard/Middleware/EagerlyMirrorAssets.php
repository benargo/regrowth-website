<?php

namespace App\Http\Integrations\Blizzard\Middleware;

use App\Contracts\Http\Integrations\Blizzard\MirrorsAssets;
use App\Http\Integrations\Blizzard\Attributes\EagerlyMirrorsAssets;
use ReflectionClass;
use Saloon\Http\Response;
use Spatie\LaravelData\Data;
use Throwable;

class EagerlyMirrorAssets
{
    /** @var array<class-string, bool> */
    private static array $cache = [];

    /**
     * Warm the on-disk CDN cache for every asset in the response DTO.
     *
     * Skips requests not annotated with {@see EagerlyMirrorsAssets}. Any DTO
     * deserialization failure is reported and swallowed so the response
     * pipeline continues uninterrupted.
     */
    public function __invoke(Response $response): void
    {
        $request = $response->getPendingRequest()->getRequest();

        if (! self::isEager($request::class)) {
            return;
        }

        try {
            $dto = $response->dto();
        } catch (Throwable $e) {
            report($e);

            return;
        }

        $this->walk($dto);
    }

    /**
     * Returns true if the request class carries the {@see EagerlyMirrorsAssets} attribute.
     *
     * Result is memoised per class-string to avoid repeated reflection on
     * subsequent calls for the same request type.
     */
    private static function isEager(string $class): bool
    {
        return self::$cache[$class] ??= (new ReflectionClass($class))
            ->getAttributes(EagerlyMirrorsAssets::class) !== [];
    }

    /**
     * Recursively traverse a DTO graph, calling {@see MirrorsAssets::mirroredUrl()}
     * on every leaf that implements the contract.
     *
     * Handles three node types:
     * - {@see MirrorsAssets}: terminal — triggers cache warm then stops descent.
     * - {@see Data}: Spatie DTO — recurses into all public properties.
     * - array: recurses into every element.
     */
    private function walk(mixed $node): void
    {
        if ($node instanceof MirrorsAssets) {
            $node->mirroredUrl();

            return;
        }

        if ($node instanceof Data) {
            foreach (get_object_vars($node) as $value) {
                $this->walk($value);
            }

            return;
        }

        if (is_array($node)) {
            foreach ($node as $value) {
                $this->walk($value);
            }
        }
    }
}
