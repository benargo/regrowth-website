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

    // ==================== zoneIds ====================

    #[Test]
    public function zone_ids_returns_null_when_absent(): void
    {
        $request = $this->makeRequest();

        $this->assertNull($request->zoneIds());
    }

    #[Test]
    public function zone_ids_returns_null_for_all(): void
    {
        $request = $this->makeRequest(['zone_ids' => 'all']);

        $this->assertNull($request->zoneIds());
    }

    #[Test]
    public function zone_ids_returns_empty_array_for_none(): void
    {
        $request = $this->makeRequest(['zone_ids' => 'none']);

        $this->assertSame([], $request->zoneIds());
    }

    #[Test]
    public function zone_ids_returns_array_of_integers(): void
    {
        $request = $this->makeRequest(['zone_ids' => '1,2,3']);

        $this->assertSame([1, 2, 3], $request->zoneIds());
    }

    // ==================== guildTagIds ====================

    #[Test]
    public function guild_tag_ids_returns_null_when_absent(): void
    {
        $request = $this->makeRequest();

        $this->assertNull($request->guildTagIds());
    }

    #[Test]
    public function guild_tag_ids_returns_empty_array_for_none(): void
    {
        $request = $this->makeRequest(['guild_tag_ids' => 'none']);

        $this->assertSame([], $request->guildTagIds());
    }

    #[Test]
    public function guild_tag_ids_returns_array_of_integers(): void
    {
        $request = $this->makeRequest(['guild_tag_ids' => '4,5']);

        $this->assertSame([4, 5], $request->guildTagIds());
    }

    // ==================== days ====================

    #[Test]
    public function days_returns_null_when_absent(): void
    {
        $request = $this->makeRequest();

        $this->assertNull($request->days());
    }

    #[Test]
    public function days_returns_null_for_all(): void
    {
        $request = $this->makeRequest(['days' => 'all']);

        $this->assertNull($request->days());
    }

    #[Test]
    public function days_returns_empty_array_for_none(): void
    {
        $request = $this->makeRequest(['days' => 'none']);

        $this->assertSame([], $request->days());
    }

    #[Test]
    public function days_returns_array_of_integers(): void
    {
        $request = $this->makeRequest(['days' => '0,1,5']);

        $this->assertSame([0, 1, 5], $request->days());
    }

    // ==================== rules: structure ====================

    #[Test]
    public function rules_includes_all_expected_keys(): void
    {
        $this->mockNoMinDate();

        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('zone_ids', $rules);
        $this->assertArrayHasKey('guild_tag_ids', $rules);
        $this->assertArrayHasKey('days', $rules);
        $this->assertArrayHasKey('since_date', $rules);
        $this->assertArrayHasKey('before_date', $rules);
    }

    // ==================== rules: date ordering ====================

    #[Test]
    public function rules_adds_before_or_equal_constraint_to_since_date_when_before_date_is_filled(): void
    {
        $this->mockNoMinDate();

        $rules = $this->makeRequest(['before_date' => '2025-06-01'])->rules();

        $this->assertContains('before_or_equal:before_date', $rules['since_date']);
    }

    #[Test]
    public function rules_does_not_add_before_or_equal_constraint_to_since_date_when_before_date_is_absent(): void
    {
        $this->mockNoMinDate();

        $rules = $this->makeRequest()->rules();

        $this->assertNotContains('before_or_equal:before_date', $rules['since_date']);
    }

    #[Test]
    public function rules_adds_after_or_equal_constraint_to_before_date_when_since_date_is_filled(): void
    {
        $this->mockNoMinDate();

        $rules = $this->makeRequest(['since_date' => '2025-01-01'])->rules();

        $this->assertContains('after_or_equal:since_date', $rules['before_date']);
    }

    #[Test]
    public function rules_does_not_add_after_or_equal_constraint_to_before_date_when_since_date_is_absent(): void
    {
        $this->mockNoMinDate();

        $rules = $this->makeRequest()->rules();

        $this->assertNotContains('after_or_equal:since_date', $rules['before_date']);
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

        $this->assertContains('after_or_equal:'.$expectedMinDate, $rules['since_date']);
        $this->assertContains('after_or_equal:'.$expectedMinDate, $rules['before_date']);
    }

    #[Test]
    public function rules_does_not_add_after_or_equal_min_date_when_no_reports_exist(): void
    {
        $this->mockNoMinDate();

        $rules = $this->makeRequest()->rules();

        $sinceHasMinDate = collect($rules['since_date'])->contains(fn ($r) => str_starts_with((string) $r, 'after_or_equal:20'));
        $beforeHasMinDate = collect($rules['before_date'])->contains(fn ($r) => str_starts_with((string) $r, 'after_or_equal:20'));

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

        $this->assertContains('before_or_equal:'.$today, $rules['since_date']);
        $this->assertContains('before_or_equal:'.$today, $rules['before_date']);
    }
}
