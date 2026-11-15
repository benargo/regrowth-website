<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\InflateGzipRequest;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[Group('platform')]
class InflateGzipRequestTest extends TestCase
{
    #[Group('happy-path')]
    #[Test]
    public function it_inflates_a_gzipped_json_body(): void
    {
        $request = $this->gzippedRequest(gzencode(json_encode(['foo' => 'bar', 'n' => [1, 2]])));

        $response = (new InflateGzipRequest)->handle($request, fn (Request $request) => response()->json($request->all()));

        $this->assertSame(['foo' => 'bar', 'n' => [1, 2]], $response->getData(true));
        $this->assertFalse($request->headers->has('Content-Encoding'));
    }

    #[Test]
    public function it_reads_the_inflated_body_as_json_without_a_json_content_type(): void
    {
        $request = Request::create('/', 'POST', server: ['HTTP_CONTENT_ENCODING' => 'gzip'], content: gzencode(json_encode(['foo' => 'bar'])));

        $response = (new InflateGzipRequest)->handle($request, fn (Request $request) => response()->json($request->all()));

        $this->assertSame(['foo' => 'bar'], $response->getData(true));
    }

    #[Test]
    public function it_passes_an_uncompressed_body_without_the_required_mode(): void
    {
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"foo":"bar"}');

        $response = (new InflateGzipRequest)->handle($request, fn (Request $request) => response()->json($request->all()));

        $this->assertSame(['foo' => 'bar'], $response->getData(true));
    }

    #[Test]
    public function it_rejects_an_invalid_gzip_stream_with_400(): void
    {
        $this->assertAbortsWith(400, $this->gzippedRequest('not gzip'));
    }

    #[Test]
    public function it_rejects_inflated_json_that_does_not_decode_with_400(): void
    {
        $this->assertAbortsWith(400, $this->gzippedRequest(gzencode('{broken')));
    }

    #[Test]
    public function it_rejects_a_gzip_stream_missing_its_trailer_with_400(): void
    {
        $gz = gzencode(json_encode(['foo' => 'bar']));

        $this->assertAbortsWith(400, $this->gzippedRequest(substr($gz, 0, -8)));
    }

    #[Test]
    public function it_rejects_a_truncated_gzip_stream_with_400(): void
    {
        $gz = gzencode(json_encode(['data' => bin2hex(random_bytes(2000))]));

        $this->assertAbortsWith(400, $this->gzippedRequest(substr($gz, 0, intdiv(strlen($gz), 2))));
    }

    // ==================== handle — size limits ====================

    #[Test]
    public function it_rejects_a_body_that_inflates_past_4_mb_with_413(): void
    {
        $this->assertAbortsWith(413, $this->gzippedRequest(gzencode(json_encode(str_repeat(' ', 4_194_305)))));
    }

    #[Test]
    public function it_rejects_a_compressed_body_over_1_mb_with_413(): void
    {
        $request = $this->gzippedRequest(random_bytes(1_048_577));

        $this->assertFalse($request->headers->has('Content-Length'));
        $this->assertAbortsWith(413, $request);
    }

    #[Test]
    public function it_rejects_a_content_length_over_1_mb_before_reading_the_body(): void
    {
        $request = $this->gzippedRequest(gzencode('{"ok":true}'));
        $request->headers->set('Content-Length', '1048577');

        $this->assertAbortsWith(413, $request);
    }

    // ==================== handle — required mode ====================

    #[Test]
    public function it_rejects_an_uncompressed_body_with_400_in_required_mode(): void
    {
        $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"ok":true}');

        $response = (new InflateGzipRequest)->handle($request, fn () => $this->fail('The request should not be passed on.'), 'required');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            ['status' => 'gzip_required', 'message' => 'The request body must be gzip-compressed.'],
            json_decode($response->getContent(), true),
        );
    }

    #[Test]
    public function it_inflates_a_gzipped_body_in_required_mode(): void
    {
        $request = $this->gzippedRequest(gzencode(json_encode(['foo' => 'bar'])));

        $response = (new InflateGzipRequest)->handle($request, fn (Request $request) => response()->json($request->all()), 'required');

        $this->assertSame(['foo' => 'bar'], $response->getData(true));
    }

    // ==================== global string middleware ====================

    #[Test]
    public function it_keeps_the_global_string_middleware_off_gzip_bodies(): void
    {
        foreach ([new TrimStrings, new ConvertEmptyStringsToNull] as $middleware) {
            $request = $this->unreadableRequest([
                'HTTP_CONTENT_ENCODING' => 'gzip',
                'CONTENT_TYPE' => 'application/json',
            ]);

            $response = $middleware->handle($request, fn () => response('ok'));

            $this->assertSame('ok', $response->getContent());
        }
    }

    // ==================== helpers ====================

    private function gzippedRequest(string $content): Request
    {
        return Request::create('/', 'POST', server: [
            'HTTP_CONTENT_ENCODING' => 'gzip',
            'CONTENT_TYPE' => 'application/json',
        ], content: $content);
    }

    /**
     * Build a POST request with the given server variables whose body must never be read.
     *
     * @param  array<string, string>  $server
     */
    private function unreadableRequest(array $server): Request
    {
        /** @var Request&MockInterface $request */
        $request = Mockery::mock(Request::class.'[getContent]', [[], [], [], [], [], ['REQUEST_METHOD' => 'POST', ...$server]]);
        $request->shouldNotReceive('getContent');

        return $request;
    }

    private function assertAbortsWith(int $status, Request $request): void
    {
        try {
            (new InflateGzipRequest)->handle($request, fn () => response('ok'));
            $this->fail('Expected the request to be aborted.');
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }
}
