<?php

namespace Tests\Feature\Http\Requests;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Http\Requests\Raid\AttendanceMatrixRequest;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Zone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsAttendanceFixtures;
use Tests\TestCase;

#[Group('raiding')]
class AttendanceMatrixRequestTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
        $this->gameVersion = $this->makeAttendanceVersion();
        $this->makeReport($this->gameVersion, Carbon::parse('2025-01-05 20:00', 'Europe/Paris'));
    }

    // ==================== game version ====================

    #[Test]
    public function game_version_is_the_newest_attendance_version_by_default(): void
    {
        $newer = $this->makeAttendanceVersion(['release_date' => '2024-06-01']);
        $this->makeReport($newer, '2025-01-01 20:00');

        $this->assertTrue($this->request()->gameVersion()->is($newer));
    }

    #[Test]
    public function game_version_prefers_the_session_choice(): void
    {
        $newer = $this->makeAttendanceVersion(['release_date' => '2024-06-01']);
        $this->makeReport($newer, '2025-01-01 20:00');
        session([ResolveCurrentGameVersion::SESSION_KEY => $this->gameVersion->slug]);

        $this->assertTrue($this->request()->gameVersion()->is($this->gameVersion));
    }

    // ==================== id lists ====================

    #[Test]
    #[DataProvider('idListKeys')]
    public function id_lists_default_to_null(string $key, string $accessor): void
    {
        $this->assertNull($this->request()->{$accessor}());
    }

    #[Test]
    #[DataProvider('idListKeys')]
    public function id_lists_treat_all_as_null(string $key, string $accessor): void
    {
        $this->assertNull($this->request([$key => 'all'])->{$accessor}());
    }

    #[Test]
    #[DataProvider('idListKeys')]
    public function id_lists_treat_none_as_an_empty_list(string $key, string $accessor): void
    {
        $this->assertSame([], $this->request([$key => 'none'])->{$accessor}());
    }

    #[Test]
    public function rank_ids_keep_the_versions_ranks_and_drop_others(): void
    {
        $own = $this->makeRank($this->gameVersion);
        $foreign = $this->makeRank($this->makeAttendanceVersion());

        $this->assertSame([$own->id], $this->request(['rank_ids' => "{$own->id},{$foreign->id}"])->rankIds());
    }

    #[Test]
    public function zone_ids_keep_zones_used_by_the_versions_reports(): void
    {
        $used = Zone::factory()->create();
        $unused = Zone::factory()->create();
        $this->makeReport($this->gameVersion, '2025-01-08 20:00', attributes: ['zone_id' => $used->id]);

        $this->assertSame([$used->id], $this->request(['zone_ids' => "{$unused->id},{$used->id}"])->zoneIds());
    }

    #[Test]
    public function guild_tag_ids_keep_tags_used_by_the_versions_reports(): void
    {
        $used = $this->makeTag($this->gameVersion);
        $this->makeReport($this->gameVersion, '2025-01-08 20:00', $used);
        $unused = $this->makeTag($this->gameVersion);

        $this->assertSame([$used->id], $this->request(['guild_tag_ids' => "{$used->id},{$unused->id}"])->guildTagIds());
    }

    #[Test]
    public function an_id_list_with_no_id_in_the_version_falls_back_to_null(): void
    {
        $foreign = $this->makeRank($this->makeAttendanceVersion());

        $this->assertNull($this->request(['rank_ids' => (string) $foreign->id])->rankIds());
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('idListKeys')]
    public function it_rejects_a_malformed_id_list(string $key, string $accessor): void
    {
        $this->expectException(ValidationException::class);

        $this->request([$key => '1,,x']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function idListKeys(): array
    {
        return [
            'ranks' => ['rank_ids', 'rankIds'],
            'zones' => ['zone_ids', 'zoneIds'],
            'guild tags' => ['guild_tag_ids', 'guildTagIds'],
        ];
    }

    // ==================== character and toggles ====================

    #[Test]
    public function character_is_null_when_absent(): void
    {
        $this->assertNull($this->request()->character());
    }

    #[Test]
    public function character_returns_a_character_of_the_version(): void
    {
        $character = $this->makeCharacter($this->gameVersion);

        $this->assertTrue($this->request(['character' => $character->id])->character()->is($character));
    }

    #[Test]
    public function character_drops_a_character_of_another_version(): void
    {
        $foreign = $this->makeCharacter($this->makeAttendanceVersion());

        $this->assertNull($this->request(['character' => $foreign->id])->character());
    }

    #[Test]
    public function combine_linked_characters_defaults_to_true(): void
    {
        $this->assertTrue($this->request()->combineLinkedCharacters());
    }

    #[Test]
    #[DataProvider('booleanInputs')]
    public function combine_linked_characters_reads_the_given_value(string $input, bool $expected): void
    {
        $this->assertSame($expected, $this->request(['combine_linked_characters' => $input])->combineLinkedCharacters());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function booleanInputs(): array
    {
        return ['true' => ['1', true], 'false' => ['0', false]];
    }

    // ==================== dates ====================

    #[Test]
    public function since_date_is_the_requested_date(): void
    {
        $this->assertSame('2025-01-10', $this->request(['since_date' => '2025-01-10'])->sinceDate()->toDateString());
    }

    #[Test]
    public function before_date_is_the_requested_date(): void
    {
        $this->assertSame('2025-01-10', $this->request(['before_date' => '2025-01-10'])->beforeDate()->toDateString());
    }

    #[Test]
    public function earliest_date_is_the_day_before_the_versions_first_report(): void
    {
        $this->makeReport($this->makeAttendanceVersion(), Carbon::parse('2024-06-01 20:00', 'Europe/Paris'));

        $this->assertSame('2025-01-04', $this->request()->earliestDate());
    }

    // ==================== rules ====================

    #[Test]
    public function rules_cover_every_filter(): void
    {
        $this->assertEqualsCanonicalizing(
            ['character', 'rank_ids', 'zone_ids', 'guild_tag_ids', 'since_date', 'before_date', 'combine_linked_characters'],
            array_keys($this->request()->rules()),
        );
    }

    #[Test]
    public function rules_bound_dates_by_the_versions_earliest_report_and_today(): void
    {
        $rules = $this->request()->rules();

        foreach (['since_date', 'before_date'] as $key) {
            $this->assertContains('after_or_equal:2025-01-04', $rules[$key]);
            $this->assertContains('before_or_equal:'.today()->toDateString(), $rules[$key]);
        }
    }

    #[Test]
    public function rules_leave_dates_unbounded_below_without_a_version(): void
    {
        GuildTag::query()->update(['count_attendance' => false]);

        $rules = $this->request()->rules();

        $this->assertEmpty(array_filter($rules['since_date'], fn ($rule) => str_starts_with((string) $rule, 'after_or_equal:20')));
    }

    #[Test]
    public function rules_order_since_and_before_when_both_are_given(): void
    {
        $rules = $this->request(['since_date' => '2025-01-06', 'before_date' => '2025-01-10'])->rules();

        $this->assertContains('before_or_equal:before_date', $rules['since_date']);
        $this->assertContains('after_or_equal:since_date', $rules['before_date']);
    }

    #[Test]
    public function rules_skip_the_order_rules_when_one_date_is_missing(): void
    {
        $rules = $this->request()->rules();

        $this->assertNotContains('before_or_equal:before_date', $rules['since_date']);
        $this->assertNotContains('after_or_equal:since_date', $rules['before_date']);
    }

    // ==================== filters ====================

    #[Test]
    public function filters_return_the_page_filter_shape_with_null_defaults(): void
    {
        $this->assertSame([
            'character' => null,
            'rank_ids' => null,
            'zone_ids' => null,
            'guild_tag_ids' => null,
            'since_date' => null,
            'before_date' => null,
            'combine_linked_characters' => true,
        ], $this->request()->filters());
    }

    #[Test]
    public function filters_include_the_character_id_and_name(): void
    {
        $character = $this->makeCharacter($this->gameVersion, attributes: ['name' => 'Thrall']);

        $this->assertSame(['id' => $character->id, 'name' => 'Thrall'], $this->request(['character' => $character->id])->filters()['character']);
    }

    #[Test]
    public function filters_echo_the_submitted_dates(): void
    {
        $filters = $this->request(['since_date' => '2025-01-06', 'before_date' => '2025-01-10'])->filters();

        $this->assertSame('2025-01-06', $filters['since_date']);
        $this->assertSame('2025-01-10', $filters['before_date']);
    }

    // ==================== cacheKey ====================

    #[Test]
    public function cache_key_starts_with_the_prefix_and_version_id(): void
    {
        $this->assertStringStartsWith("attendance:matrix:{$this->gameVersion->id}:", $this->request()->cacheKey('attendance:matrix:'));
    }

    #[Test]
    public function cache_key_is_deterministic(): void
    {
        $this->assertSame($this->request()->cacheKey('p:'), $this->request()->cacheKey('p:'));
    }

    #[Test]
    public function cache_key_differs_per_version(): void
    {
        $first = $this->request()->cacheKey('p:');
        $other = $this->makeAttendanceVersion();
        $this->makeReport($other, '2025-01-01 20:00');
        session([ResolveCurrentGameVersion::SESSION_KEY => $other->slug]);

        $this->assertNotSame($first, $this->request()->cacheKey('p:'));
    }

    #[Test]
    #[DataProvider('differingFilters')]
    public function cache_key_differs_when_a_filter_differs(string $case): void
    {
        [$left, $right] = $this->differingInputs($case);

        $this->assertNotSame($this->request($left)->cacheKey('p:'), $this->request($right)->cacheKey('p:'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function differingFilters(): array
    {
        return collect([
            'character set vs unset',
            'different characters',
            'different ranks',
            'zones null vs empty',
            'different zones',
            'different tags',
            'since set vs unset',
            'different since',
            'before set vs unset',
            'different before',
            'combine on vs off',
        ])->mapWithKeys(fn (string $case): array => [$case => [$case]])->all();
    }

    #[Test]
    #[DataProvider('idListKeys')]
    public function cache_key_ignores_id_order(string $key, string $accessor): void
    {
        [$first, $second] = $this->twoIdsFor($key);

        $this->assertSame(
            $this->request([$key => "{$first},{$second}"])->cacheKey('p:'),
            $this->request([$key => "{$second},{$first}"])->cacheKey('p:'),
        );
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $input
     */
    private function request(array $input = []): AttendanceMatrixRequest
    {
        $request = AttendanceMatrixRequest::create('/raiding/attendance/matrix', 'GET', $input);
        $request->setContainer($this->app)->setRedirector($this->app->make(Redirector::class));
        $request->validateResolved();

        return $request;
    }

    /**
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function differingInputs(string $case): array
    {
        return match ($case) {
            'character set vs unset' => [[], ['character' => $this->makeCharacter($this->gameVersion)->id]],
            'different characters' => [['character' => $this->makeCharacter($this->gameVersion)->id], ['character' => $this->makeCharacter($this->gameVersion)->id]],
            'different ranks' => $this->singleIdInputs('rank_ids'),
            'zones null vs empty' => [[], ['zone_ids' => 'none']],
            'different zones' => $this->singleIdInputs('zone_ids'),
            'different tags' => $this->singleIdInputs('guild_tag_ids'),
            'since set vs unset' => [[], ['since_date' => '2025-01-06']],
            'different since' => [['since_date' => '2025-01-06'], ['since_date' => '2025-01-07']],
            'before set vs unset' => [[], ['before_date' => '2025-01-10']],
            'different before' => [['before_date' => '2025-01-10'], ['before_date' => '2025-01-11']],
            'combine on vs off' => [['combine_linked_characters' => '1'], ['combine_linked_characters' => '0']],
        };
    }

    /**
     * @return array{array<string, string>, array<string, string>}
     */
    private function singleIdInputs(string $key): array
    {
        [$first, $second] = $this->twoIdsFor($key);

        return [[$key => (string) $first], [$key => (string) $second]];
    }

    /**
     * @return array{int, int}
     */
    private function twoIdsFor(string $key): array
    {
        return match ($key) {
            'rank_ids' => [$this->makeRank($this->gameVersion)->id, $this->makeRank($this->gameVersion)->id],
            'zone_ids' => collect([1, 2])->map(fn () => $this->makeReport($this->gameVersion, '2025-01-08 20:00', attributes: ['zone_id' => Zone::factory()->create()->id])->zone_id)->all(),
            'guild_tag_ids' => collect([1, 2])->map(fn () => $this->makeReport($this->gameVersion, '2025-01-08 20:00')->guild_tag_id)->all(),
        };
    }
}
