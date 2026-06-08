<?php

namespace App\Http\Integrations\Blizzard\Requests;

use App\Http\Integrations\Blizzard\Responses\FetchAssetResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\Support\Uri;
use InvalidArgumentException;
use Saloon\Enums\Method;
use Saloon\Http\Request;

abstract class RenderRequest extends Request
{
    protected Method $method = Method::GET;

    protected ?string $response = FetchAssetResponse::class;

    protected ?string $endpoint = null;

    protected int $size;

    /**
     * A Uri argument is always treated as an absolute render URL. A string argument
     * keeps the dual-mode behaviour: an absolute URL is validated here, while a bare
     * icon-name / portrait-path is handled by the subclass.
     *
     * @throws InvalidArgumentException if an absolute URL is given that is invalid
     *                                  or does not belong to the Blizzard render CDN
     */
    public function __construct(Uri|string $input, ?int $size = null)
    {
        if ($size) {
            $this->size = $size;
        }

        if ($input instanceof Uri) {
            $this->handleUri($input);

            return;
        }

        Str::of($input)->whenContains('://', fn (Stringable $string) => $this->handleUri($string->toUri()));
    }

    /**
     * Return the host-relative path for this asset request.
     * For icon-name inputs, boot() overwrites the URL via setUrl() after construction.
     */
    public function resolveEndpoint(): string
    {
        return $this->endpoint ?? '/';
    }

    /**
     * Validate and apply an absolute Blizzard render CDN URL.
     *
     * @throws InvalidArgumentException
     */
    protected function handleUri(Uri $uri): void
    {
        $host = (string) $uri->host();

        if ($host !== 'render.worldofwarcraft.com'
            && ! (str_starts_with($host, 'render-') && str_ends_with($host, '.worldofwarcraft.com'))
        ) {
            throw new InvalidArgumentException(
                "FetchIconRequest requires a Blizzard render URL; got: {$uri}",
            );
        }

        $path = (string) $uri->path();

        if ($path === '' || $path === '/') {
            throw new InvalidArgumentException(
                "FetchIconRequest requires a URL with a non-empty path; got: {$uri}",
            );
        }

        $this->endpoint = Str::start($path, '/');
    }
}
