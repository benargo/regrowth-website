<?php

namespace Tests\Unit\Http\Requests\Raid;

use App\Http\Requests\Raid\ReportsIndexRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportsIndexRequestTest extends TestCase
{
    private function makeRequest(array $params = []): ReportsIndexRequest
    {
        return ReportsIndexRequest::create('/', 'GET', $params);
    }

    private function mockNoMinDate(): void
    {
        $taggedCache = \Mockery::mock();
        $taggedCache->shouldReceive('remember')->andReturn(null);
        Cache::shouldReceive('tags')->with(['reports'])->andReturn($taggedCache);
    }

    private function mockMinDate(string $rawStartTime): void
    {
        $taggedCache = \Mockery::mock();
        $taggedCache->shouldReceive('remember')->andReturn($rawStartTime);
        Cache::shouldReceive('tags')->with(['reports'])->andReturn($taggedCache);
    }

    // ==================== rules: structure ====================

    #[Test]
    public function rules_includes_all_expected_keys(): void
    {
        $this->mockNoMinDate();

        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('filter.zone_ids', $rules);
        $this->assertArrayHasKey('filter.guild_tag_ids', $rules);
        $this->assertArrayHasKey('filter.days', $rules);
        $this->assertArrayHasKey('filter.since_date', $rules);
        $this->assertArrayHasKey('filter.before_date', $rules);
    }

    // ==================== rules: date ordering ====================

    #[Test]
    public function rules_adds_before_or_equal_constraint_to_since_date_when_before_date_is_filled(): void
    {
        $this->mockNoMinDate();

        $rules = $this->makeRequest(['filter' => ['before_date' => '2025-06-01']])->rules();

        $this->assertContains('before_or_equal:filter.before_date', $rules['filter.since_date']);
    }

    #[Test]
    public function rules_does_not_add_before_or_equal_constraint_to_since_date_when_before_date_is_absent(): void
    {
        $this->mockNoMinDate();

        $rules = $this->makeRequest()->rules();

        $this->assertNotContains('before_or_equal:filter.before_date', $rules['filter.since_date']);
    }

    #[Test]
    public function rules_adds_after_or_equal_constraint_to_before_date_when_since_date_is_filled(): void
    {
        $this->mockNoMinDate();

        $rules = $this->makeRequest(['filter' => ['since_date' => '2025-01-01']])->rules();

        $this->assertContains('after_or_equal:filter.since_date', $rules['filter.before_date']);
    }

    #[Test]
    public function rules_does_not_add_after_or_equal_constraint_to_before_date_when_since_date_is_absent(): void
    {
        $this->mockNoMinDate();

        $rules = $this->makeRequest()->rules();

        $this->assertNotContains('after_or_equal:filter.since_date', $rules['filter.before_date']);
    }

    // ==================== rules: min date ====================

    #[Test]
    public function rules_adds_after_or_equal_min_date_to_both_date_fields_when_reports_exist(): void
    {
        $rawStartTime = '2025-06-15 12:00:00';
        $this->mockMinDate($rawStartTime);

        $expectedMinDate = Carbon::parse($rawStartTime, 'UTC')
            ->timezone(config('app.timezone'))
            ->subDay()
            ->toDateString();

        $rules = $this->makeRequest()->rules();

        $this->assertContains('after_or_equal:'.$expectedMinDate, $rules['filter.since_date']);
        $this->assertContains('after_or_equal:'.$expectedMinDate, $rules['filter.before_date']);
    }

    #[Test]
    public function rules_does_not_add_after_or_equal_min_date_when_no_reports_exist(): void
    {
        $this->mockNoMinDate();

        $rules = $this->makeRequest()->rules();

        $sinceHasMinDate = collect($rules['filter.since_date'])->contains(fn ($r) => str_starts_with((string) $r, 'after_or_equal:20'));
        $beforeHasMinDate = collect($rules['filter.before_date'])->contains(fn ($r) => str_starts_with((string) $r, 'after_or_equal:20'));

        $this->assertFalse($sinceHasMinDate);
        $this->assertFalse($beforeHasMinDate);
    }

    // ==================== rules: before_or_equal today ====================

    #[Test]
    public function rules_includes_before_or_equal_today_for_both_date_fields(): void
    {
        $this->mockNoMinDate();

        $today = Carbon::today(config('app.timezone'))->toDateString();

        $rules = $this->makeRequest()->rules();

        $this->assertContains('before_or_equal:'.$today, $rules['filter.since_date']);
        $this->assertContains('before_or_equal:'.$today, $rules['filter.before_date']);
    }
}
