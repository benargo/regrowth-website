<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Exceptions;

use App\Http\Integrations\WarcraftLogs\Exceptions\ApiException;
use App\Http\Integrations\WarcraftLogs\Exceptions\WarcraftLogsRequestException;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\WarcraftLogs\Fixtures\ProbeRequest;
use Tests\Unit\Http\Integrations\WarcraftLogs\WarcraftLogsTestCase;

#[Group('warcraftlogs-integration')]
class ApiExceptionTest extends WarcraftLogsTestCase
{
    #[Test]
    #[Group('contract')]
    public function it_is_a_saloon_request_exception_and_a_warcraft_logs_request_exception(): void
    {
        $exception = $this->captureApiException(WarcraftLogsNamespace::Retail, 500);

        $this->assertInstanceOf(RequestException::class, $exception);
        $this->assertInstanceOf(WarcraftLogsRequestException::class, $exception);
    }

    // ==================== context ====================

    #[Test]
    #[Group('error-handling')]
    #[DataProvider('namespaceHosts')]
    public function context_reports_the_host_of_the_namespace_that_was_called(WarcraftLogsNamespace $namespace, string $host): void
    {
        $exception = $this->captureApiException($namespace, 500);

        $this->assertSame($host, $exception->context()['host']);
    }

    #[Test]
    #[Group('error-handling')]
    #[DataProvider('failureStatuses')]
    public function context_reports_the_response_status(int $status): void
    {
        $exception = $this->captureApiException(WarcraftLogsNamespace::Retail, $status);

        $this->assertSame(['host' => 'www.warcraftlogs.com', 'status' => $status], $exception->context());
    }

    #[Test]
    #[Group('error-handling')]
    public function context_is_available_for_a_non_json_error_page(): void
    {
        $exception = $this->captureApiException(
            WarcraftLogsNamespace::Classic,
            502,
            MockResponse::make('<html><body>502 Bad Gateway</body></html>', 502, ['Content-Type' => 'text/html']),
        );

        $this->assertSame(['host' => 'classic.warcraftlogs.com', 'status' => 502], $exception->context());
    }

    // ==================== helpers ====================

    /**
     * @return array<string, array{WarcraftLogsNamespace, string}>
     */
    public static function namespaceHosts(): array
    {
        return [
            'retail' => [WarcraftLogsNamespace::Retail, 'www.warcraftlogs.com'],
            'classic' => [WarcraftLogsNamespace::Classic, 'classic.warcraftlogs.com'],
            'anniversary' => [WarcraftLogsNamespace::Anniversary, 'fresh.warcraftlogs.com'],
        ];
    }

    /**
     * @return array<string, array{int}>
     */
    public static function failureStatuses(): array
    {
        return [
            'unauthorized' => [401],
            'not found' => [404],
            'server error' => [500],
            'bad gateway' => [502],
        ];
    }

    private function captureApiException(WarcraftLogsNamespace $namespace, int $status, ?MockResponse $response = null): ApiException
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            ProbeRequest::class => $response ?? MockResponse::make(['message' => 'boom'], $status),
        ]);

        try {
            $this->makeConnector()->send(new ProbeRequest($namespace));
        } catch (ApiException $exception) {
            return $exception;
        }

        $this->fail('Expected an ApiException.');
    }
}
