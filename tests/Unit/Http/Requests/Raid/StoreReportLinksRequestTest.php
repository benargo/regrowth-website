<?php

namespace Tests\Unit\Http\Requests\Raid;

use App\Http\Requests\Raid\StoreReportLinksRequest;
use App\Models\WarcraftLogs\Report;
use Illuminate\Routing\Route;
use Tests\TestCase;

class StoreReportLinksRequestTest extends TestCase
{
    private function makeRequest(Report $report, array $data = []): StoreReportLinksRequest
    {
        $request = StoreReportLinksRequest::create('/', 'POST', $data);

        $route = \Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('report', null)->andReturn($report);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    // ==================== rules ====================

    public function test_rules_requires_codes_array(): void
    {
        $report = \Mockery::mock(Report::class);
        $report->shouldReceive('getAttribute')->with('code')->andReturn('ABC123');

        $rules = $this->makeRequest($report)->rules();

        $this->assertArrayHasKey('codes', $rules);
        $this->assertContains('required', $rules['codes']);
        $this->assertContains('array', $rules['codes']);
        $this->assertContains('min:1', $rules['codes']);
    }

    public function test_rules_requires_each_code_to_be_a_string_that_exists_in_reports(): void
    {
        $report = \Mockery::mock(Report::class);
        $report->shouldReceive('getAttribute')->with('code')->andReturn('ABC123');

        $rules = $this->makeRequest($report)->rules();

        $this->assertArrayHasKey('codes.*', $rules);
        $this->assertContains('required', $rules['codes.*']);
        $this->assertContains('string', $rules['codes.*']);
        $this->assertContains('exists:wcl_reports,code', $rules['codes.*']);
    }

    public function test_rules_excludes_current_report_code(): void
    {
        $report = \Mockery::mock(Report::class);
        $report->shouldReceive('getAttribute')->with('code')->andReturn('MYCODE');

        $rules = $this->makeRequest($report)->rules();

        $this->assertContains('not_in:MYCODE', $rules['codes.*']);
    }

    public function test_rules_excludes_null_when_report_has_no_code(): void
    {
        $report = \Mockery::mock(Report::class);
        $report->shouldReceive('getAttribute')->with('code')->andReturn(null);

        $rules = $this->makeRequest($report)->rules();

        $this->assertContains('not_in:', $rules['codes.*']);
    }

    // ==================== authorize ====================

    public function test_authorize_returns_true_when_user_can_update_report(): void
    {
        $report = \Mockery::mock(Report::class);
        $report->shouldReceive('getAttribute')->with('code')->andReturn('ABC123');

        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('update', $report)->andReturn(true);

        $request = $this->makeRequest($report);
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_returns_false_when_user_cannot_update_report(): void
    {
        $report = \Mockery::mock(Report::class);
        $report->shouldReceive('getAttribute')->with('code')->andReturn('ABC123');

        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('update', $report)->andReturn(false);

        $request = $this->makeRequest($report);
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }
}
