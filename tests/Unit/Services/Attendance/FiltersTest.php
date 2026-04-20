<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Character;
use App\Models\WarcraftLogs\GuildTag;
use App\Services\Attendance\Filters;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FiltersTest extends TestCase
{
    use RefreshDatabase;

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

    // ==================== CSV parsing (via fromArray) ====================

    #[Test]
    public function zone_ids_default_to_null_when_absent(): void
    {
        $filters = Filters::fromArray([]);

        $this->assertNull($filters->zoneIds);
    }

    #[Test]
    public function zone_ids_parse_all_as_null(): void
    {
        $filters = Filters::fromArray(['zone_ids' => 'all']);

        $this->assertNull($filters->zoneIds);
    }

    #[Test]
    public function zone_ids_parse_none_as_empty_array(): void
    {
        $filters = Filters::fromArray(['zone_ids' => 'none']);

        $this->assertSame([], $filters->zoneIds);
    }

    #[Test]
    public function zone_ids_parse_csv_into_array_of_integers(): void
    {
        $filters = Filters::fromArray(['zone_ids' => '1,2,3']);

        $this->assertSame([1, 2, 3], $filters->zoneIds);
    }

    #[Test]
    public function guild_tag_ids_default_when_absent_uses_count_attendance_tags(): void
    {
        $counting = GuildTag::factory()->create(['count_attendance' => true]);
        GuildTag::factory()->create(['count_attendance' => false]);

        $filters = Filters::fromArray([]);

        $this->assertSame([$counting->id], $filters->guildTagIds);
    }

    #[Test]
    public function guild_tag_ids_parse_none_as_empty_array(): void
    {
        $filters = Filters::fromArray(['guild_tag_ids' => 'none']);

        $this->assertSame([], $filters->guildTagIds);
    }

    #[Test]
    public function guild_tag_ids_parse_csv_into_array_of_integers(): void
    {
        $filters = Filters::fromArray(['guild_tag_ids' => '4,5']);

        $this->assertSame([4, 5], $filters->guildTagIds);
    }

    #[Test]
    public function rank_ids_default_to_empty_array_when_absent(): void
    {
        $filters = Filters::fromArray([]);

        $this->assertSame([], $filters->rankIds);
    }

    #[Test]
    public function rank_ids_parse_none_as_empty_array(): void
    {
        $filters = Filters::fromArray(['rank_ids' => 'none']);

        $this->assertSame([], $filters->rankIds);
    }

    #[Test]
    public function rank_ids_parse_csv_into_array_of_integers(): void
    {
        $filters = Filters::fromArray(['rank_ids' => '10,20']);

        $this->assertSame([10, 20], $filters->rankIds);
    }

    // ==================== combine_linked_characters ====================

    #[Test]
    public function combine_linked_characters_defaults_to_true_when_absent(): void
    {
        $filters = Filters::fromArray([]);

        $this->assertTrue($filters->includeLinkedCharacters);
    }

    #[Test]
    public function combine_linked_characters_returns_true_when_set_to_true(): void
    {
        $filters = Filters::fromArray(['combine_linked_characters' => '1']);

        $this->assertTrue($filters->includeLinkedCharacters);
    }

    #[Test]
    public function combine_linked_characters_returns_false_when_set_to_false(): void
    {
        $filters = Filters::fromArray(['combine_linked_characters' => '0']);

        $this->assertFalse($filters->includeLinkedCharacters);
    }

    // ==================== character ====================

    #[Test]
    public function character_defaults_to_null_when_absent(): void
    {
        $filters = Filters::fromArray([]);

        $this->assertNull($filters->character);
    }

    #[Test]
    public function character_is_hydrated_from_character_id(): void
    {
        $character = Character::factory()->create();

        $filters = Filters::fromArray(['character' => $character->id]);

        $this->assertSame($character->id, $filters->character?->id);
    }

    // ==================== rules: structure ====================

    #[Test]
    public function rules_includes_all_expected_keys(): void
    {
        $this->mockNoMinDate();

        $rules = Filters::rules();

        $this->assertArrayHasKey('character', $rules);
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

        $rules = Filters::rules(['before_date' => '2025-06-01']);

        $this->assertContains('before_or_equal:before_date', $rules['since_date']);
    }

    #[Test]
    public function rules_does_not_add_before_or_equal_constraint_to_since_date_when_before_date_is_absent(): void
    {
        $this->mockNoMinDate();

        $rules = Filters::rules();

        $this->assertNotContains('before_or_equal:before_date', $rules['since_date']);
    }

    #[Test]
    public function rules_adds_after_or_equal_constraint_to_before_date_when_since_date_is_filled(): void
    {
        $this->mockNoMinDate();

        $rules = Filters::rules(['since_date' => '2025-01-01']);

        $this->assertContains('after_or_equal:since_date', $rules['before_date']);
    }

    #[Test]
    public function rules_does_not_add_after_or_equal_constraint_to_before_date_when_since_date_is_absent(): void
    {
        $this->mockNoMinDate();

        $rules = Filters::rules();

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

        $rules = Filters::rules();

        $this->assertContains('after_or_equal:'.$expectedMinDate, $rules['since_date']);
        $this->assertContains('after_or_equal:'.$expectedMinDate, $rules['before_date']);
    }

    #[Test]
    public function rules_does_not_add_after_or_equal_min_date_when_no_reports_exist(): void
    {
        $this->mockNoMinDate();

        $rules = Filters::rules();

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

        $rules = Filters::rules();

        $this->assertContains('before_or_equal:'.$today, $rules['since_date']);
        $this->assertContains('before_or_equal:'.$today, $rules['before_date']);
    }

    // ==================== toArray / jsonSerialize ====================

    #[Test]
    public function to_array_returns_resolved_dto_shape(): void
    {
        $filters = new Filters(
            rankIds: [1, 2, 3],
            zoneIds: [10, 20],
            guildTagIds: [7],
            sinceDate: Carbon::parse('2025-01-05 05:00:00', 'UTC'),
            beforeDate: Carbon::parse('2025-02-10 05:00:00', 'UTC'),
            includeLinkedCharacters: true,
        );

        $this->assertSame([
            'character' => null,
            'rank_ids' => [1, 2, 3],
            'zone_ids' => [10, 20],
            'guild_tag_ids' => [7],
            'since_date' => '2025-01-05',
            'before_date' => '2025-02-10',
            'combine_linked_characters' => true,
        ], $filters->toArray());
    }

    #[Test]
    public function json_serialize_matches_to_array(): void
    {
        $filters = new Filters(rankIds: [1, 2], includeLinkedCharacters: false);

        $this->assertSame($filters->toArray(), $filters->jsonSerialize());
    }

    // ==================== validate ====================

    #[Test]
    public function validate_returns_a_filters_instance_for_valid_input(): void
    {
        $this->mockNoMinDate();

        $filters = Filters::validate(['since_date' => '2024-01-01']);

        $this->assertInstanceOf(Filters::class, $filters);
        $this->assertNotNull($filters->sinceDate);
    }

    #[Test]
    public function validate_throws_a_validation_exception_for_invalid_input(): void
    {
        $this->mockNoMinDate();

        $this->expectException(ValidationException::class);

        Filters::validate(['since_date' => 'not-a-date']);
    }

    #[Test]
    public function to_array_includes_character_id_and_name_when_set(): void
    {
        $character = Character::factory()->create(['name' => 'Thrall']);

        $filters = new Filters(character: $character);

        $this->assertSame([
            'id' => $character->id,
            'name' => 'Thrall',
        ], $filters->toArray()['character']);
    }

    // ==================== cacheKey ====================

    #[Test]
    public function cache_key_returns_string_with_given_prefix(): void
    {
        $filters = new Filters;

        $key = $filters->cacheKey('my_prefix:');

        $this->assertStringStartsWith('my_prefix:', $key);
    }

    #[Test]
    public function cache_key_is_deterministic_for_same_filters(): void
    {
        $filters = new Filters(
            rankIds: [1, 2],
            zoneIds: [10],
            guildTagIds: [7],
            sinceDate: Carbon::parse('2025-01-05 05:00:00', 'UTC'),
            beforeDate: Carbon::parse('2025-02-10 05:00:00', 'UTC'),
            includeLinkedCharacters: true,
        );

        $this->assertSame($filters->cacheKey('prefix:'), $filters->cacheKey('prefix:'));
    }

    #[Test]
    public function cache_key_differs_when_prefix_differs(): void
    {
        $filters = new Filters;

        $this->assertNotSame($filters->cacheKey('a:'), $filters->cacheKey('b:'));
    }

    #[Test]
    public function cache_key_differs_when_character_differs(): void
    {
        $charA = Character::factory()->create();
        $charB = Character::factory()->create();

        $keyA = (new Filters(character: $charA))->cacheKey('prefix:');
        $keyB = (new Filters(character: $charB))->cacheKey('prefix:');

        $this->assertNotSame($keyA, $keyB);
    }

    #[Test]
    public function cache_key_differs_when_character_is_null_vs_set(): void
    {
        $character = Character::factory()->create();

        $keyNull = (new Filters)->cacheKey('prefix:');
        $keySet = (new Filters(character: $character))->cacheKey('prefix:');

        $this->assertNotSame($keyNull, $keySet);
    }

    #[Test]
    public function cache_key_differs_when_rank_ids_differ(): void
    {
        $keyA = (new Filters(rankIds: [1, 2]))->cacheKey('prefix:');
        $keyB = (new Filters(rankIds: [3, 4]))->cacheKey('prefix:');

        $this->assertNotSame($keyA, $keyB);
    }

    #[Test]
    public function cache_key_is_same_regardless_of_rank_ids_order(): void
    {
        $keyA = (new Filters(rankIds: [1, 2]))->cacheKey('prefix:');
        $keyB = (new Filters(rankIds: [2, 1]))->cacheKey('prefix:');

        $this->assertSame($keyA, $keyB);
    }

    #[Test]
    public function cache_key_differs_when_zone_ids_differ(): void
    {
        $keyA = (new Filters(zoneIds: [10]))->cacheKey('prefix:');
        $keyB = (new Filters(zoneIds: [20]))->cacheKey('prefix:');

        $this->assertNotSame($keyA, $keyB);
    }

    #[Test]
    public function cache_key_differs_when_zone_ids_is_null_vs_empty(): void
    {
        $keyNull = (new Filters(zoneIds: null))->cacheKey('prefix:');
        $keyEmpty = (new Filters(zoneIds: []))->cacheKey('prefix:');

        $this->assertNotSame($keyNull, $keyEmpty);
    }

    #[Test]
    public function cache_key_is_same_regardless_of_zone_ids_order(): void
    {
        $keyA = (new Filters(zoneIds: [10, 20]))->cacheKey('prefix:');
        $keyB = (new Filters(zoneIds: [20, 10]))->cacheKey('prefix:');

        $this->assertSame($keyA, $keyB);
    }

    #[Test]
    public function cache_key_differs_when_guild_tag_ids_differ(): void
    {
        $keyA = (new Filters(guildTagIds: [7]))->cacheKey('prefix:');
        $keyB = (new Filters(guildTagIds: [8]))->cacheKey('prefix:');

        $this->assertNotSame($keyA, $keyB);
    }

    #[Test]
    public function cache_key_is_same_regardless_of_guild_tag_ids_order(): void
    {
        $keyA = (new Filters(guildTagIds: [7, 8]))->cacheKey('prefix:');
        $keyB = (new Filters(guildTagIds: [8, 7]))->cacheKey('prefix:');

        $this->assertSame($keyA, $keyB);
    }

    #[Test]
    public function cache_key_differs_when_since_date_differs(): void
    {
        $keyA = (new Filters(sinceDate: Carbon::parse('2025-01-01', 'UTC')))->cacheKey('prefix:');
        $keyB = (new Filters(sinceDate: Carbon::parse('2025-06-01', 'UTC')))->cacheKey('prefix:');

        $this->assertNotSame($keyA, $keyB);
    }

    #[Test]
    public function cache_key_differs_when_since_date_is_null_vs_set(): void
    {
        $keyNull = (new Filters(sinceDate: null))->cacheKey('prefix:');
        $keySet = (new Filters(sinceDate: Carbon::parse('2025-01-01', 'UTC')))->cacheKey('prefix:');

        $this->assertNotSame($keyNull, $keySet);
    }

    #[Test]
    public function cache_key_differs_when_before_date_differs(): void
    {
        $keyA = (new Filters(beforeDate: Carbon::parse('2025-03-01', 'UTC')))->cacheKey('prefix:');
        $keyB = (new Filters(beforeDate: Carbon::parse('2025-09-01', 'UTC')))->cacheKey('prefix:');

        $this->assertNotSame($keyA, $keyB);
    }

    #[Test]
    public function cache_key_differs_when_before_date_is_null_vs_set(): void
    {
        $keyNull = (new Filters(beforeDate: null))->cacheKey('prefix:');
        $keySet = (new Filters(beforeDate: Carbon::parse('2025-03-01', 'UTC')))->cacheKey('prefix:');

        $this->assertNotSame($keyNull, $keySet);
    }

    #[Test]
    public function cache_key_differs_when_include_linked_characters_differs(): void
    {
        $keyTrue = (new Filters(includeLinkedCharacters: true))->cacheKey('prefix:');
        $keyFalse = (new Filters(includeLinkedCharacters: false))->cacheKey('prefix:');

        $this->assertNotSame($keyTrue, $keyFalse);
    }
}
