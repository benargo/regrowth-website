<?php

namespace Tests\Unit\Http\Requests\Raid;

use App\Http\Requests\Raid\AttendanceMatrixRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceMatrixRequestTest extends TestCase
{
    private function makeRequest(array $params = []): AttendanceMatrixRequest
    {
        return AttendanceMatrixRequest::create('/', 'GET', $params);
    }

    private function mockNoMinDate(): void
    {
        $taggedCache = Mockery::mock();
        $taggedCache->shouldReceive('remember')->andReturn(null);
        Cache::shouldReceive('tags')->with(['attendance', 'reports'])->andReturn($taggedCache);
    }

    private function mockMinDate(string $rawStartTime): void
    {
        $taggedCache = Mockery::mock();
        $taggedCache->shouldReceive('remember')->andReturn($rawStartTime);
        Cache::shouldReceive('tags')->with(['attendance', 'reports'])->andReturn($taggedCache);
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

    // ==================== rankIds ====================

    #[Test]
    public function rank_ids_returns_null_when_absent(): void
    {
        $request = $this->makeRequest();

        $this->assertNull($request->rankIds());
    }

    #[Test]
    public function rank_ids_returns_empty_array_for_none(): void
    {
        $request = $this->makeRequest(['rank_ids' => 'none']);

        $this->assertSame([], $request->rankIds());
    }

    #[Test]
    public function rank_ids_returns_array_of_integers(): void
    {
        $request = $this->makeRequest(['rank_ids' => '10,20']);

        $this->assertSame([10, 20], $request->rankIds());
    }

    // ==================== combineLinkedCharacters ====================

    #[Test]
    public function combine_linked_characters_defaults_to_true_when_absent(): void
    {
        $request = $this->makeRequest();

        $this->assertTrue($request->combineLinkedCharacters());
    }

    #[Test]
    public function combine_linked_characters_returns_true_when_set(): void
    {
        $request = $this->makeRequest(['combine_linked_characters' => '1']);

        $this->assertTrue($request->combineLinkedCharacters());
    }

    #[Test]
    public function combine_linked_characters_returns_false_when_set_to_false(): void
    {
        $request = $this->makeRequest(['combine_linked_characters' => '0']);

        $this->assertFalse($request->combineLinkedCharacters());
    }

    // ==================== rules: structure ====================

    #[Test]
    public function rules_includes_all_expected_keys(): void
    {
        $this->mockNoMinDate();

        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('rank_ids', $rules);
        $this->assertArrayHasKey('zone_ids', $rules);
        $this->assertArrayHasKey('guild_tag_ids', $rules);
        $this->assertArrayHasKey('since_date', $rules);
        $this->assertArrayHasKey('before_date', $rules);
        $this->assertArrayHasKey('combine_linked_characters', $rules);
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
            ->timezone(config('app.timezone', 'UTC'))
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

        $today = Carbon::today(config('app.timezone', 'UTC'))->toDateString();

        $rules = $this->makeRequest()->rules();

        $this->assertContains('before_or_equal:'.$today, $rules['since_date']);
        $this->assertContains('before_or_equal:'.$today, $rules['before_date']);
    }
}
