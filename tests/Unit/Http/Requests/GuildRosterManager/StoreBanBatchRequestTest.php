<?php

namespace Tests\Unit\Http\Requests\GuildRosterManager;

use App\Actions\GuildRosterManager\ListExistingBanGuids;
use App\Data\GuildRosterManager\BanData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Http\Requests\GuildRosterManager\StoreBanBatchRequest;
use App\Models\GuildRosterManager\Ban;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class StoreBanBatchRequestTest extends TestCase
{
    private const int GAME_VERSION_ID = 7;

    // ==================== kind ====================

    #[Test]
    public function kind_is_ban(): void
    {
        $this->assertSame(RosterEntryKind::Ban, new StoreBanBatchRequest()->kind());
    }

    // ==================== authorize ====================

    #[Test]
    #[Group('authorization')]
    public function authorize_denies_a_user_who_may_not_write_to_the_sync(): void
    {
        ListExistingBanGuids::shouldNotRun();

        $request = $this->makeRequest([['guid' => 'Player-1-0A']], mayWriteSync: false, mayCreate: true, mayUpdate: true);

        $this->assertFalse($request->authorize());
    }

    #[Test]
    #[Group('authorization')]
    public function authorize_denies_a_user_who_may_neither_create_nor_update_bans(): void
    {
        ListExistingBanGuids::shouldNotRun();

        $request = $this->makeRequest([['guid' => 'Player-1-0A']], mayWriteSync: true, mayCreate: false, mayUpdate: false);

        $this->assertFalse($request->authorize());
    }

    /**
     * @param  array<int, string>  $existingKeys
     */
    #[Test]
    #[DataProvider('singleBanPermissions')]
    public function authorize_needs_create_for_a_new_ban_and_update_for_an_existing_one(array $existingKeys, bool $mayCreate, bool $mayUpdate, bool $expected): void
    {
        $this->fakeExistingKeys(['Player-1-0A'], $existingKeys);

        $request = $this->makeRequest([['guid' => 'Player-1-0A']], mayWriteSync: true, mayCreate: $mayCreate, mayUpdate: $mayUpdate);

        $this->assertSame($expected, $request->authorize());
    }

    /**
     * @return array<string, array{array<int, string>, bool, bool, bool}>
     */
    public static function singleBanPermissions(): array
    {
        return [
            'new ban with create' => [[], true, false, true],
            'new ban with only update' => [[], false, true, false],
            'existing ban with update' => [['Player-1-0A'], false, true, true],
            'existing ban with only create' => [['Player-1-0A'], true, false, false],
        ];
    }

    #[Test]
    public function authorize_allows_a_mixed_batch_when_the_user_may_create_and_update(): void
    {
        $this->fakeExistingKeys(['Player-1-0A', 'Player-1-0B'], ['Player-1-0A']);

        $request = $this->makeRequest([['guid' => 'Player-1-0A'], ['guid' => 'Player-1-0B']], mayWriteSync: true, mayCreate: true, mayUpdate: true);

        $this->assertTrue($request->authorize());
    }

    #[Test]
    #[Group('authorization')]
    public function authorize_denies_a_mixed_batch_when_one_ban_needs_a_missing_permission(): void
    {
        $this->fakeExistingKeys(['Player-1-0A', 'Player-1-0B'], ['Player-1-0A']);

        $request = $this->makeRequest([['guid' => 'Player-1-0A'], ['guid' => 'Player-1-0B']], mayWriteSync: true, mayCreate: false, mayUpdate: true);

        $this->assertFalse($request->authorize());
    }

    #[Test]
    public function authorize_looks_up_only_the_string_guids_of_array_rows(): void
    {
        $this->fakeExistingKeys(['Player-1-0A'], []);

        $request = $this->makeRequest(['not a row', ['name' => 'Nameless'], ['guid' => 42], ['guid' => 'Player-1-0A']], mayWriteSync: true, mayCreate: true, mayUpdate: false);

        $this->assertTrue($request->authorize());
    }

    // ==================== rules ====================

    #[Test]
    #[Group('validation')]
    public function it_requires_the_guid_unbanned_and_epoch_of_a_row(): void
    {
        $errors = $this->errorsFor([['name' => 'Troublemaker']]);

        $this->assertSame(['rows.0.guid', 'rows.0.unbanned', 'rows.0.epoch'], $errors->keys());
    }

    #[Test]
    #[Group('validation')]
    public function it_accepts_a_row_without_a_ban_date_banner_or_reason(): void
    {
        $errors = $this->errorsFor([$this->row(['bannedAt' => null, 'bannedBy' => null, 'reason' => null])]);

        $this->assertSame([], $errors->toArray());
    }

    #[Test]
    #[Group('validation')]
    public function it_accepts_a_75_character_multibyte_reason(): void
    {
        $errors = $this->errorsFor([$this->row(['reason' => str_repeat('é', 75)])]);

        $this->assertSame([], $errors->toArray());
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('invalidValues')]
    public function it_rejects_an_invalid_value(string $key, mixed $value, string $message): void
    {
        $errors = $this->errorsFor([$this->row([$key => $value])]);

        $this->assertSame(["rows.0.{$key}" => [$message]], $errors->toArray());
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidValues(): array
    {
        return [
            'lowercase guid' => ['guid', 'Player-1-0a', 'The rows.0.guid field format is invalid.'],
            'non-player guid' => ['guid', 'Creature-1-0A', 'The rows.0.guid field format is invalid.'],
            'name over 64 characters' => ['name', str_repeat('a', 65), 'The rows.0.name field must not be greater than 64 characters.'],
            'non-boolean unbanned' => ['unbanned', 'maybe', 'The rows.0.unbanned field must be true or false.'],
            'banned at with an offset' => ['bannedAt', '2026-01-02T10:00:00+01:00', 'The rows.0.bannedAt field must match the format Y-m-d\TH:i:s\Z.'],
            'banned at without a time' => ['bannedAt', '2026-01-02', 'The rows.0.bannedAt field must match the format Y-m-d\TH:i:s\Z.'],
            'banned by over 64 characters' => ['bannedBy', str_repeat('a', 65), 'The rows.0.bannedBy field must not be greater than 64 characters.'],
            'reason over 75 characters' => ['reason', str_repeat('a', 76), 'The rows.0.reason field must not be greater than 75 characters.'],
            'negative epoch' => ['epoch', -1, 'The rows.0.epoch field must be at least 0.'],
        ];
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_two_rows_with_the_same_guid(): void
    {
        $errors = $this->errorsFor([$this->row(), $this->row()]);

        $this->assertSame(['rows.0.guid', 'rows.1.guid'], $errors->keys());
        $this->assertSame(['The rows.0.guid field has a duplicate value.'], $errors->get('rows.0.guid'));
    }

    // ==================== rows ====================

    #[Test]
    #[Group('happy-path')]
    public function rows_hydrates_each_validated_row_as_ban_data(): void
    {
        $rows = $this->validatedRequest([$this->row()])->rows();

        $this->assertEquals(
            [new BanData(
                guid: 'Player-1-0A',
                name: 'Troublemaker',
                unbanned: false,
                banned_at: '2026-01-02T10:00:00Z',
                banned_by: 'Officer',
                reason: 'Ninja looting',
                epoch: 1700000000,
            )],
            $rows,
        );
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return [
            'guid' => 'Player-1-0A',
            'name' => 'Troublemaker',
            'unbanned' => false,
            'bannedAt' => '2026-01-02T10:00:00Z',
            'bannedBy' => 'Officer',
            'reason' => 'Ninja looting',
            'epoch' => 1700000000,
            ...$overrides,
        ];
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function errorsFor(array $rows): MessageBag
    {
        try {
            $this->validatedRequest($rows);
        } catch (ValidationException $exception) {
            return $exception->validator->errors();
        }

        return new MessageBag;
    }

    /**
     * Build the request around the rows for a user who may write to the sync and create or update any ban, and run its full validation, throwing when it fails.
     *
     * @param  array<int, mixed>  $rows
     */
    private function validatedRequest(array $rows): StoreBanBatchRequest
    {
        ListExistingBanGuids::shouldRun()->andReturn(collect());

        $request = $this->makeRequest($rows, mayWriteSync: true, mayCreate: true, mayUpdate: true);
        $request->setContainer($this->app)->setRedirector($this->app->make(Redirector::class));

        $request->validateResolved();

        return $request;
    }

    /**
     * Fake the existing ban lookup for the sync's game version, expecting the given guids and returning the existing ones.
     *
     * @param  array<int, string>  $guids
     * @param  array<int, string>  $existingKeys
     */
    private function fakeExistingKeys(array $guids, array $existingKeys): void
    {
        ListExistingBanGuids::shouldRun()
            ->once()
            ->with(self::GAME_VERSION_ID, Mockery::on(fn (Collection $keys): bool => $keys->all() === $guids))
            ->andReturn(collect($existingKeys));
    }

    /**
     * Build a ban batch request for a sync, with a user holding the given abilities.
     *
     * @param  array<int, mixed>  $rows
     */
    private function makeRequest(array $rows, bool $mayWriteSync, bool $mayCreate, bool $mayUpdate): StoreBanBatchRequest
    {
        $sync = new Upload;
        $sync->game_version_id = self::GAME_VERSION_ID;

        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->with('writeSync', [$sync, null])->andReturn($mayWriteSync);
        $user->shouldReceive('can')->with('create', Ban::class)->andReturn($mayCreate);
        $user->shouldReceive('can')->with('update', Ban::class)->andReturn($mayUpdate);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('sync', null)->andReturn($sync);

        $request = StoreBanBatchRequest::create('/', 'POST', ['rows' => $rows]);
        $request->setUserResolver(fn (): User => $user);
        $request->setRouteResolver(fn (): Route => $route);

        return $request;
    }
}
