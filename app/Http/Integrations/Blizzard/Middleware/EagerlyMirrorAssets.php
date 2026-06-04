<?php

namespace App\Http\Integrations\Blizzard\Middleware;

use App\Http\Integrations\Blizzard\Attributes\EagerlyMirrorsAssets;
use App\Http\Integrations\Blizzard\Data\Media\AssetData;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Http\Integrations\Blizzard\Responses\FetchAssetResponse;
use ReflectionClass;
use Saloon\Http\Response;
use Throwable;

class EagerlyMirrorAssets
{
    /** @var array<class-string, bool> */
    private static array $cache = [];

    public function __construct(
        private readonly RenderConnector $renderConnector
    ) {}

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

        collect($dto->assets)->each(function (AssetData $asset): void {
            try {
                /** @var FetchAssetResponse $fetchResponse */
                $fetchResponse = $this->renderConnector->send(new FetchAssetRequest($asset->value));
                $mirroredPath = $fetchResponse->mirroredPath();

                if ($mirroredPath !== null) {
                    $asset->setMirroredPath($mirroredPath);
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
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
}
