<?php

namespace App\Http\Integrations\Blizzard\Requests;

use App\Http\Integrations\Blizzard\Responses\FetchAssetResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
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
     * @throws InvalidArgumentException if an absolute URL is given that is invalid
     *                                  or does not belong to the Blizzard render CDN
     */
    public function __construct(string $input, ?int $size = null)
    {
        if ($size) {
            $this->size = $size;
        }

        Str::of($input)->whenContains('://', fn (Stringable $string) => $this->handleUri($string));
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
     * Parse, validate, and apply an absolute Blizzard render CDN URL.
     *
     * @throws InvalidArgumentException
     */
    protected function handleUri(Stringable $string): void
    {
        $uri = $string->toUri();
        $host = $uri->host();

        if ($host !== 'render.worldofwarcraft.com'
            && ! (str_starts_with($host, 'render-') && str_ends_with($host, '.worldofwarcraft.com'))
        ) {
            throw new InvalidArgumentException(
                "FetchIconRequest requires a Blizzard render URL; got: {$string}",
            );
        }

        $path = $uri->path();

        if ($path === '' || $path === '/') {
            throw new InvalidArgumentException(
                "FetchIconRequest requires a URL with a non-empty path; got: {$string}",
            );
        }

        $this->endpoint = Str::start($path, '/');
    }
}
