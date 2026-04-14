<?php

namespace Tests\Unit\Http\Requests\Raid;

use App\Http\Requests\Raid\DestroyReportLinksRequest;
use App\Models\Raids\Report;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DestroyReportLinksRequestTest extends TestCase
{
    private function makeRequest(Report $report): DestroyReportLinksRequest
    {
        $request = DestroyReportLinksRequest::create('/', 'PATCH');

        $route = \Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('report', null)->andReturn($report);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    // ==================== rules ====================

    #[Test]
    public function rules_returns_empty_array(): void
    {
        $report = \Mockery::mock(Report::class);

        $rules = $this->makeRequest($report)->rules();

        $this->assertSame([], $rules);
    }

    // ==================== authorize ====================

    #[Test]
    public function authorize_returns_true_when_user_can_update_report(): void
    {
        $report = \Mockery::mock(Report::class);

        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('update', $report)->andReturn(true);

        $request = $this->makeRequest($report);
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function authorize_returns_false_when_user_cannot_update_report(): void
    {
        $report = \Mockery::mock(Report::class);

        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('update', $report)->andReturn(false);

        $request = $this->makeRequest($report);
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }
}
