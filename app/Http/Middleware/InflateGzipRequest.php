<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inflates gzip-encoded JSON request bodies and marks the request as JSON.
 *
 * Without a mode, an uncompressed body passes through untouched. In `required` mode, a request without
 * `Content-Encoding: gzip` is answered with `400 gzip_required` without being passed on.
 *
 * The compressed-size limit is checked before the body is inflated: first against the `Content-Length` header,
 * then against the body's actual length. Either one above the limit is refused with `413`. The inflated output is
 * refused with `413` once it passes the inflated limit.
 */
class InflateGzipRequest
{
    public const MAX_COMPRESSED_BYTES = 1_048_576;

    public const MAX_INFLATED_BYTES = 4_194_304;

    private const INPUT_CHUNK_BYTES = 4096;

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        if (strtolower((string) $request->header('Content-Encoding')) !== 'gzip') {
            if ($mode === 'required') {
                return response()->json([
                    'status' => 'gzip_required',
                    'message' => 'The request body must be gzip-compressed.',
                ], 400);
            }

            return $next($request);
        }

        $contentLength = $request->header('Content-Length');

        if ($contentLength !== null && (int) $contentLength > self::MAX_COMPRESSED_BYTES) {
            abort(413, 'The request body is too large.');
        }

        $compressed = $request->getContent();

        if (strlen($compressed) > self::MAX_COMPRESSED_BYTES) {
            abort(413, 'The request body is too large.');
        }

        $decoded = json_decode($this->inflate($compressed), true);

        if (! is_array($decoded)) {
            abort(400, 'The request body is not valid JSON.');
        }

        $request->setJson(new InputBag($decoded));
        $request->headers->remove('Content-Encoding');
        $request->headers->set('Content-Type', 'application/json');

        return $next($request);
    }

    /**
     * Inflate the gzip stream in chunks, aborting once the output passes the inflated limit.
     */
    private function inflate(string $compressed): string
    {
        $context = @inflate_init(ZLIB_ENCODING_GZIP);

        if ($context === false) {
            abort(400, 'The request body could not be inflated.');
        }

        $inflated = '';

        foreach (str_split($compressed, self::INPUT_CHUNK_BYTES) as $chunk) {
            $output = @inflate_add($context, $chunk);

            if ($output === false) {
                abort(400, 'The request body is not a valid gzip stream.');
            }

            if (strlen($inflated) + strlen($output) > self::MAX_INFLATED_BYTES) {
                abort(413, 'The request body is too large.');
            }

            $inflated .= $output;
        }

        if (inflate_get_status($context) !== ZLIB_STREAM_END) {
            abort(400, 'The request body is a truncated gzip stream.');
        }

        return $inflated;
    }
}
