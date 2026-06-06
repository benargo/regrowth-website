<?php

namespace App\Http\Controllers;

use App\Contracts\HasBlizzardIcons;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Http\Requests\ServeIconRequest;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Str;
use Throwable;

#[Middleware('signed', 'throttle:icons')]
class ServeIconController extends Controller implements HasBlizzardIcons
{
    private const LONG_CACHE = 'public, max-age=31536000, immutable';

    private const FALLBACK_CACHE = 'public, max-age=60';

    /**
     * Serve a Blizzard icon from the mirror/CDN, falling back to a bundled questionmark.
     */
    public function __invoke(ServeIconRequest $request, RenderConnector $renderConnector): Response
    {
        $size = $request->integer('size');
        $name = $request->string('name')->value();

        // The questionmark is a bundled local asset (also our fallback) — serve it directly,
        // never round-tripping to the CDN for an icon we already ship.
        // Served as JPEG regardless of the requested extension — there is no PNG variant.
        if ((string) Str::of($name)->before('.') === self::BLIZZARD_UNKNOWN_ICON) {
            return $this->icon($this->questionmarkBytes(), 'image/jpeg', self::LONG_CACHE);
        }

        try {
            $response = $renderConnector->send(new FetchAssetRequest($name, $size));

            if ($response->successful()) {
                return $this->icon(
                    $response->body(),
                    $response->header('Content-Type') ?: $this->contentType($name),
                    self::LONG_CACHE,
                );
            }
        } catch (Throwable) {
            // Fall through to the bundled fallback below.
        }

        return $this->icon($this->questionmarkBytes(), 'image/jpeg', self::FALLBACK_CACHE)
            ->setStatusCode(404);
    }

    /**
     * Helper to create a response with the given bytes, content type, and cache control headers.
     */
    private function icon(string $bytes, string $contentType, string $cacheControl): Response
    {
        return response($bytes)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', $cacheControl);
    }

    /**
     * The questionmark icon is a bundled local asset, served as JPEG regardless of the requested extension — there is no PNG variant.
     */
    private function questionmarkBytes(): string
    {
        return (string) file_get_contents(resource_path('images/inv_misc_questionmark.jpg'));
    }

    /**
     * Determine the content type based on the file extension, defaulting to JPEG if unknown.
     */
    private function contentType(string $name): string
    {
        return match (Str::of($name)->afterLast('.')->lower()->value()) {
            'png' => 'image/png',
            default => 'image/jpeg',
        };
    }
}
