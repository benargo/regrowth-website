<?php

namespace Tests\Unit\Http\Requests\Raid;

use App\Http\Requests\Raid\UpdateReportRequest;
use App\Models\Raids\Report;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\NotIn;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateReportRequestTest extends TestCase
{
    private function makeRequest(string $reportId = 'some-uuid'): UpdateReportRequest
    {
        $report = \Mockery::mock(Report::class);
        $report->shouldReceive('getKey')->andReturn($reportId);

        $route = \Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->withArgs(fn ($param) => $param === 'report')->andReturn($report);

        $request = new UpdateReportRequest;
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    // ==================== rules ====================

    #[Test]
    public function rules_links_is_optional(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('links', $rules);
        $this->assertContains('sometimes', $rules['links']);
        $this->assertContains('array', $rules['links']);
        $this->assertNotContains('required', $rules['links']);
    }

    #[Test]
    public function rules_requires_links_action_when_links_present(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('links.action', $rules);
        $this->assertContains('required_with:links', $rules['links.action']);
        $this->assertContains('string', $rules['links.action']);

        $inRule = collect($rules['links.action'])->first(fn ($r) => $r instanceof In);
        $this->assertNotNull($inRule, 'links.action rules should contain a Rule::in validator');
    }

    #[Test]
    public function rules_validates_each_link_id_exists_and_excludes_current_report(): void
    {
        $reportId = 'test-report-uuid';
        $rules = $this->makeRequest($reportId)->rules();

        $this->assertArrayHasKey('links.link_ids.*', $rules);
        $this->assertContains('required', $rules['links.link_ids.*']);
        $this->assertContains('string', $rules['links.link_ids.*']);
        $this->assertContains('exists:raid_reports,id', $rules['links.link_ids.*']);

        $notInRule = collect($rules['links.link_ids.*'])->first(fn ($r) => $r instanceof NotIn);
        $this->assertNotNull($notInRule, 'links.link_ids.* rules should contain a Rule::notIn validator');
    }

    #[Test]
    public function rules_loot_councillors_is_optional(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('loot_councillors', $rules);
        $this->assertContains('sometimes', $rules['loot_councillors']);
        $this->assertContains('array', $rules['loot_councillors']);
        $this->assertNotContains('required', $rules['loot_councillors']);
    }

    #[Test]
    public function rules_requires_loot_councillors_action_when_present(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('loot_councillors.action', $rules);
        $this->assertContains('required_with:loot_councillors', $rules['loot_councillors.action']);
        $this->assertContains('string', $rules['loot_councillors.action']);

        $inRule = collect($rules['loot_councillors.action'])->first(fn ($r) => $r instanceof In);
        $this->assertNotNull($inRule, 'loot_councillors.action rules should contain a Rule::in validator');
    }

    #[Test]
    public function rules_requires_loot_councillors_character_ids_when_present(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('loot_councillors.character_ids', $rules);
        $this->assertContains('required_with:loot_councillors', $rules['loot_councillors.character_ids']);
        $this->assertContains('array', $rules['loot_councillors.character_ids']);
        $this->assertContains('min:1', $rules['loot_councillors.character_ids']);
    }

    // ==================== authorize ====================

    #[Test]
    public function authorize_returns_true_when_user_can_update_report(): void
    {
        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('update', \Mockery::any())->andReturn(true);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function authorize_returns_false_when_user_cannot_update_report(): void
    {
        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('update', \Mockery::any())->andReturn(false);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }
}
