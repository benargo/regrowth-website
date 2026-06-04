<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Exceptions;

use App\Http\Integrations\Blizzard\Exceptions\ApiException;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Http\Response;

class ApiExceptionTest extends TestCase
{
    private Response $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->response = Mockery::mock(Response::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function make(
        string $method = 'GET',
        string $endpoint = '/data/wow/item/1',
        int $status = 400,
        ?string $code = null,
        ?array $body = null,
    ): ApiException {
        return new ApiException($method, $endpoint, $status, $this->response, $code, $body);
    }

    // ==================== inheritance ====================

    #[Test]
    public function it_extends_client_exception(): void
    {
        $this->assertTrue(is_subclass_of(ApiException::class, ClientException::class));
    }

    #[Test]
    public function it_implements_blizzard_request_exception(): void
    {
        $this->assertTrue(is_subclass_of(ApiException::class, BlizzardRequestException::class));
    }

    // ==================== constructor property assignment ====================

    #[Test]
    public function it_stores_method(): void
    {
        $e = $this->make(method: 'POST');

        $this->assertSame('POST', $e->method);
    }

    #[Test]
    public function it_stores_endpoint(): void
    {
        $e = $this->make(endpoint: '/data/wow/item/99');

        $this->assertSame('/data/wow/item/99', $e->endpoint);
    }

    #[Test]
    public function it_stores_blizzard_status(): void
    {
        $e = $this->make(status: 403);

        $this->assertSame(403, $e->blizzardStatus);
    }

    #[Test]
    public function it_stores_blizzard_code(): void
    {
        $e = $this->make(code: 'BLZWEBAPI00000001');

        $this->assertSame('BLZWEBAPI00000001', $e->blizzardCode);
    }

    #[Test]
    public function blizzard_code_defaults_to_null(): void
    {
        $e = $this->make();

        $this->assertNull($e->blizzardCode);
    }

    #[Test]
    public function it_stores_body(): void
    {
        $body = ['code' => 'BLZWEBAPI00000001', 'detail' => 'Not found.'];
        $e = $this->make(body: $body);

        $this->assertSame($body, $e->body);
    }

    #[Test]
    public function body_defaults_to_null(): void
    {
        $e = $this->make();

        $this->assertNull($e->body);
    }

    // ==================== exception message ====================

    #[Test]
    public function it_includes_method_in_message(): void
    {
        $e = $this->make(method: 'DELETE');

        $this->assertStringContainsString('DELETE', $e->getMessage());
    }

    #[Test]
    public function it_includes_endpoint_in_message(): void
    {
        $e = $this->make(endpoint: '/data/wow/item/42');

        $this->assertStringContainsString('/data/wow/item/42', $e->getMessage());
    }

    #[Test]
    public function it_includes_blizzard_status_in_message(): void
    {
        $e = $this->make(status: 503);

        $this->assertStringContainsString('503', $e->getMessage());
    }

    // ==================== accessor methods ====================

    #[Test]
    public function get_method_returns_method(): void
    {
        $e = $this->make(method: 'PUT');

        $this->assertSame('PUT', $e->getMethod());
    }

    #[Test]
    public function get_endpoint_returns_endpoint(): void
    {
        $e = $this->make(endpoint: '/data/wow/item/7');

        $this->assertSame('/data/wow/item/7', $e->getEndpoint());
    }

    #[Test]
    public function get_blizzard_status_returns_status(): void
    {
        $e = $this->make(status: 429);

        $this->assertSame(429, $e->getBlizzardStatus());
    }

    #[Test]
    public function get_blizzard_code_returns_code(): void
    {
        $e = $this->make(code: 'BLZWEBAPI00000001');

        $this->assertSame('BLZWEBAPI00000001', $e->getBlizzardCode());
    }

    #[Test]
    public function get_blizzard_code_returns_null_when_not_set(): void
    {
        $e = $this->make();

        $this->assertNull($e->getBlizzardCode());
    }

    #[Test]
    public function get_blizzard_body_returns_body(): void
    {
        $body = ['code' => 'BLZWEBAPI00000001', 'detail' => 'Not found.'];
        $e = $this->make(body: $body);

        $this->assertSame($body, $e->getBlizzardBody());
    }

    #[Test]
    public function get_blizzard_body_returns_null_when_not_set(): void
    {
        $e = $this->make();

        $this->assertNull($e->getBlizzardBody());
    }

    // ==================== previous exception ====================

    #[Test]
    public function it_chains_a_previous_exception(): void
    {
        $previous = new RuntimeException('original error');
        $e = new ApiException('GET', '/endpoint', 500, $this->response, null, null, $previous);

        $this->assertSame($previous, $e->getPrevious());
    }
}
