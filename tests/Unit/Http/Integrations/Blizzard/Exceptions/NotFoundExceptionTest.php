<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Exceptions;

use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Exceptions\NotFoundException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Saloon\Exceptions\Request\Statuses\NotFoundException as Base;
use Saloon\Http\Response;

#[Group('blizzard-integration')]
class ConcreteNotFoundException extends NotFoundException {}

class NotFoundExceptionTest extends TestCase
{
    #[Test]
    public function it_extends_saloon_not_found_exception(): void
    {
        $this->assertInstanceOf(Base::class, $this->makeException());
    }

    #[Test]
    public function it_implements_blizzard_request_exception(): void
    {
        $this->assertTrue(is_subclass_of(NotFoundException::class, BlizzardRequestException::class));
    }

    #[Test]
    public function it_has_a_default_prefix(): void
    {
        $this->assertSame(
            'Resource not found:',
            (new \ReflectionClass(NotFoundException::class))->getDefaultProperties()['prefix'],
        );
    }

    #[Test]
    public function it_uses_the_prefix_in_the_message(): void
    {
        $exception = $this->makeException();

        $this->assertStringContainsString('Resource not found:', $exception->getMessage());
    }

    #[Test]
    public function it_includes_method_endpoint_and_status_in_the_message(): void
    {
        $exception = $this->makeException(
            method: 'GET',
            endpoint: '/data/wow/item/12345',
            blizzardStatus: 404,
        );

        $this->assertStringContainsString('GET', $exception->getMessage());
        $this->assertStringContainsString('/data/wow/item/12345', $exception->getMessage());
        $this->assertStringContainsString('404', $exception->getMessage());
    }

    // ==================== getters ====================

    #[Test]
    public function it_exposes_method_via_getter(): void
    {
        $exception = $this->makeException(method: 'POST');

        $this->assertSame('POST', $exception->getMethod());
    }

    #[Test]
    public function it_exposes_endpoint_via_getter(): void
    {
        $exception = $this->makeException(endpoint: '/data/wow/item/99');

        $this->assertSame('/data/wow/item/99', $exception->getEndpoint());
    }

    #[Test]
    public function it_exposes_blizzard_status_via_getter(): void
    {
        $exception = $this->makeException(blizzardStatus: 404);

        $this->assertSame(404, $exception->getBlizzardStatus());
    }

    #[Test]
    public function it_returns_null_blizzard_code_when_not_provided(): void
    {
        $this->assertNull($this->makeException()->getBlizzardCode());
    }

    #[Test]
    public function it_exposes_blizzard_code_when_provided(): void
    {
        $exception = $this->makeException(blizzardCode: 'BLZWEBAPI00000404');

        $this->assertSame('BLZWEBAPI00000404', $exception->getBlizzardCode());
    }

    #[Test]
    public function it_returns_null_body_when_not_provided(): void
    {
        $this->assertNull($this->makeException()->getBlizzardBody());
    }

    #[Test]
    public function it_exposes_body_when_provided(): void
    {
        $body = ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not found.'];
        $exception = $this->makeException(body: $body);

        $this->assertSame($body, $exception->getBlizzardBody());
    }

    // ==================== prefix override ====================

    #[Test]
    public function it_allows_subclasses_to_override_the_prefix(): void
    {
        $response = $this->createStub(Response::class);

        $exception = new class('GET', '/data/wow/item/1', 404, $response) extends NotFoundException
        {
            protected string $prefix = 'Custom prefix:';
        };

        $this->assertStringContainsString('Custom prefix:', $exception->getMessage());
    }

    // ==================== helpers ====================

    private function makeException(
        string $method = 'GET',
        string $endpoint = '/data/wow/item/12345',
        int $blizzardStatus = 404,
        ?string $blizzardCode = null,
        ?array $body = null,
    ): ConcreteNotFoundException {
        $response = $this->createStub(Response::class);

        return new ConcreteNotFoundException(
            method: $method,
            endpoint: $endpoint,
            blizzardStatus: $blizzardStatus,
            response: $response,
            blizzardCode: $blizzardCode,
            body: $body,
        );
    }
}
