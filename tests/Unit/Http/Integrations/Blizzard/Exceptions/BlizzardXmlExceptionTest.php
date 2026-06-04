<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Exceptions;

use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardXmlException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Http\Response;
use Saloon\XmlWrangler\XmlReader;

class BlizzardXmlExceptionTest extends TestCase
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

    private function makeReader(string $xml): XmlReader
    {
        return XmlReader::fromString($xml);
    }

    private function xmlWith(string $code, string $message): XmlReader
    {
        return $this->makeReader("<Error><Code>{$code}</Code><Message>{$message}</Message></Error>");
    }

    // ==================== inheritance ====================

    #[Test]
    public function it_extends_client_exception(): void
    {
        $this->assertTrue(is_subclass_of(BlizzardXmlException::class, ClientException::class));
    }

    #[Test]
    public function it_implements_blizzard_request_exception(): void
    {
        $this->assertTrue(is_subclass_of(BlizzardXmlException::class, BlizzardRequestException::class));
    }

    // ==================== constructor property assignment ====================

    #[Test]
    public function it_stores_method_endpoint_and_status(): void
    {
        $e = new BlizzardXmlException('GET', '/data/wow/item/1', 403, $this->response, $this->xmlWith('Code', 'Msg'));

        $this->assertSame('GET', $e->method);
        $this->assertSame('/data/wow/item/1', $e->endpoint);
        $this->assertSame(403, $e->blizzardStatus);
    }

    #[Test]
    public function it_extracts_xml_code_and_message(): void
    {
        $e = new BlizzardXmlException('GET', '/data/wow/item/1', 403, $this->response, $this->xmlWith('AccessDenied', 'Access Denied'));

        $this->assertSame('AccessDenied', $e->xmlCode);
        $this->assertSame('Access Denied', $e->xmlMessage);
    }

    #[Test]
    public function it_sets_xml_code_to_null_when_node_is_empty(): void
    {
        $reader = $this->makeReader('<Error><Code></Code><Message>Something</Message></Error>');

        $e = new BlizzardXmlException('GET', '/data/wow/item/1', 403, $this->response, $reader);

        $this->assertNull($e->xmlCode);
    }

    #[Test]
    public function it_sets_xml_message_to_null_when_node_is_empty(): void
    {
        $reader = $this->makeReader('<Error><Code>SomeCode</Code><Message></Message></Error>');

        $e = new BlizzardXmlException('GET', '/data/wow/item/1', 403, $this->response, $reader);

        $this->assertNull($e->xmlMessage);
    }

    // ==================== exception message ====================

    #[Test]
    public function it_includes_method_endpoint_and_status_in_message(): void
    {
        $e = new BlizzardXmlException('GET', '/data/wow/item/1', 403, $this->response, $this->xmlWith('AccessDenied', 'Access Denied'));

        $this->assertStringContainsString('GET', $e->getMessage());
        $this->assertStringContainsString('/data/wow/item/1', $e->getMessage());
        $this->assertStringContainsString('403', $e->getMessage());
    }

    #[Test]
    public function it_includes_xml_code_in_message(): void
    {
        $e = new BlizzardXmlException('GET', '/data/wow/item/1', 403, $this->response, $this->xmlWith('AccessDenied', 'Access Denied'));

        $this->assertStringContainsString('AccessDenied', $e->getMessage());
    }

    #[Test]
    public function it_includes_xml_message_in_message(): void
    {
        $e = new BlizzardXmlException('GET', '/data/wow/item/1', 403, $this->response, $this->xmlWith('AccessDenied', 'Access Denied'));

        $this->assertStringContainsString('Access Denied', $e->getMessage());
    }

    #[Test]
    public function it_omits_xml_suffix_from_message_when_both_nodes_are_empty(): void
    {
        $reader = $this->makeReader('<Error><Code></Code><Message></Message></Error>');

        $e = new BlizzardXmlException('GET', '/data/wow/item/1', 403, $this->response, $reader);

        $this->assertStringNotContainsString(' — ', $e->getMessage());
    }

    // ==================== accessor methods ====================

    #[Test]
    public function get_method_returns_method(): void
    {
        $e = new BlizzardXmlException('POST', '/endpoint', 500, $this->response, $this->xmlWith('Err', 'msg'));

        $this->assertSame('POST', $e->getMethod());
    }

    #[Test]
    public function get_endpoint_returns_endpoint(): void
    {
        $e = new BlizzardXmlException('GET', '/data/wow/item/99', 403, $this->response, $this->xmlWith('Err', 'msg'));

        $this->assertSame('/data/wow/item/99', $e->getEndpoint());
    }

    #[Test]
    public function get_blizzard_status_returns_status(): void
    {
        $e = new BlizzardXmlException('GET', '/endpoint', 503, $this->response, $this->xmlWith('Err', 'msg'));

        $this->assertSame(503, $e->getBlizzardStatus());
    }

    #[Test]
    public function get_blizzard_code_returns_xml_code(): void
    {
        $e = new BlizzardXmlException('GET', '/endpoint', 403, $this->response, $this->xmlWith('AccessDenied', 'Denied'));

        $this->assertSame('AccessDenied', $e->getBlizzardCode());
    }

    #[Test]
    public function get_blizzard_code_returns_null_when_node_is_empty(): void
    {
        $reader = $this->makeReader('<Error><Code></Code><Message></Message></Error>');

        $e = new BlizzardXmlException('GET', '/endpoint', 403, $this->response, $reader);

        $this->assertNull($e->getBlizzardCode());
    }

    #[Test]
    public function get_blizzard_body_returns_array_with_code_and_message(): void
    {
        $e = new BlizzardXmlException('GET', '/endpoint', 403, $this->response, $this->xmlWith('AccessDenied', 'Access Denied'));

        $this->assertSame(['code' => 'AccessDenied', 'message' => 'Access Denied'], $e->getBlizzardBody());
    }

    #[Test]
    public function get_blizzard_body_returns_null_when_both_nodes_are_empty(): void
    {
        $reader = $this->makeReader('<Error><Code></Code><Message></Message></Error>');

        $e = new BlizzardXmlException('GET', '/endpoint', 403, $this->response, $reader);

        $this->assertNull($e->getBlizzardBody());
    }

    #[Test]
    public function get_blizzard_body_omits_empty_fields(): void
    {
        $reader = $this->makeReader('<Error><Code>AccessDenied</Code><Message></Message></Error>');

        $e = new BlizzardXmlException('GET', '/endpoint', 403, $this->response, $reader);

        $this->assertSame(['code' => 'AccessDenied'], $e->getBlizzardBody());
    }
}
