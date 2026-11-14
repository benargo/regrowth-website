<?php

namespace Tests\Unit\Http\Requests\GuildRosterManager;

use App\Data\GuildRosterManager\RosterRemovalsData;
use App\Http\Requests\GuildRosterManager\CommitSyncRequest;
use App\Models\GuildRosterManager\Ban;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('characters')]
class CommitSyncRequestTest extends TestCase
{
    // ==================== authorize ====================

    #[Test]
    #[Group('authorization')]
    public function authorize_denies_a_user_who_may_not_write_to_the_sync(): void
    {
        $request = $this->makeRequest([], mayWriteSync: false);

        $this->assertFalse($request->authorize());
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[Test]
    #[DataProvider('bodiesWithoutBanRemovals')]
    public function authorize_allows_a_commit_without_ban_removals_without_checking_the_ban_update_ability(array $body): void
    {
        $request = $this->makeRequest($body, mayWriteSync: true);

        $this->assertTrue($request->authorize());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function bodiesWithoutBanRemovals(): array
    {
        return [
            'empty body' => [[]],
            'removals of other kinds' => [['removed' => ['members' => ['Player-1-0A']]]],
            'empty ban removals' => [['removed' => ['bans' => []]]],
            'non-array ban removals' => [['removed' => ['bans' => 'Player-1-0A']]],
        ];
    }

    #[Test]
    #[DataProvider('banUpdatePermissions')]
    public function authorize_needs_the_ban_update_ability_when_the_commit_removes_bans(bool $mayUpdateBans, bool $expected): void
    {
        $request = $this->makeRequest(['removed' => ['bans' => ['Player-1-0A']]], mayWriteSync: true, mayUpdateBans: $mayUpdateBans);

        $this->assertSame($expected, $request->authorize());
    }

    /**
     * @return array<string, array{bool, bool}>
     */
    public static function banUpdatePermissions(): array
    {
        return [
            'with the update ability' => [true, true],
            'without the update ability' => [false, false],
        ];
    }

    // ==================== rules ====================

    #[Test]
    #[Group('happy-path')]
    public function it_accepts_a_complete_body(): void
    {
        $errors = $this->errorsFor($this->body());

        $this->assertSame([], $errors->toArray());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_accepts_an_empty_body(): void
    {
        $errors = $this->errorsFor([]);

        $this->assertSame([], $errors->toArray());
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[Test]
    #[Group('validation')]
    #[DataProvider('invalidBodies')]
    public function it_rejects_an_invalid_value(array $body, string $key, string $message): void
    {
        $errors = $this->errorsFor($body);

        $this->assertSame([$key => [$message]], $errors->toArray());
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidBodies(): array
    {
        return [
            'empty ranks' => [['ranks' => []], 'ranks', 'The ranks field must have at least 1 items.'],
            'eleven ranks' => [['ranks' => array_fill(0, 11, 'Raider')], 'ranks', 'The ranks field must not have more than 10 items.'],
            'rank over 50 characters' => [['ranks' => [str_repeat('a', 51)]], 'ranks.0', 'The ranks.0 field must not be greater than 50 characters.'],
            'empty rank' => [['ranks' => ['']], 'ranks.0', 'The ranks.0 field is required.'],
            'non-array removed' => [['removed' => 'all'], 'removed', 'The removed field must be an array.'],
            'lowercase member guid' => [['removed' => ['members' => ['Player-1-0a']]], 'removed.members.0', 'The removed.members.0 field format is invalid.'],
            'non-player leaver guid' => [['removed' => ['leavers' => ['Creature-1-0A']]], 'removed.leavers.0', 'The removed.leavers.0 field format is invalid.'],
            'ban guid over 40 characters' => [['removed' => ['bans' => ['Player-1-'.str_repeat('A', 32)]]], 'removed.bans.0', 'The removed.bans.0 field must not be greater than 40 characters.'],
            'alt group id over 32 characters' => [['removed' => ['altGroups' => [str_repeat('1', 33)]]], 'removed.altGroups.0', 'The removed.altGroups.0 field must not be greater than 32 characters.'],
            'non-string alt group id' => [['removed' => ['altGroups' => [12]]], 'removed.altGroups.0', 'The removed.altGroups.0 field must be a string.'],
        ];
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('removalLimits')]
    public function it_limits_the_number_of_removals_per_kind(string $kind, string $label, int $limit): void
    {
        $keys = array_map(fn (int $index): string => sprintf('Player-1-%X', $index), range(1, $limit + 1));

        $errors = $this->errorsFor(['removed' => [$kind => $keys]]);

        $this->assertSame(["removed.{$kind}" => ["The removed.{$label} field must not have more than {$limit} items."]], $errors->toArray());
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function removalLimits(): array
    {
        return [
            'members' => ['members', 'members', 1000],
            'leavers' => ['leavers', 'leavers', 5000],
            'alt groups' => ['altGroups', 'alt groups', 1000],
        ];
    }

    #[Test]
    #[Group('validation')]
    #[DataProvider('removalKinds')]
    public function it_rejects_a_key_removed_twice(string $kind, string $key): void
    {
        $errors = $this->errorsFor(['removed' => [$kind => [$key, $key]]]);

        $this->assertSame(["removed.{$kind}.0", "removed.{$kind}.1"], $errors->keys());
        $this->assertSame(["The removed.{$kind}.0 field has a duplicate value."], $errors->get("removed.{$kind}.0"));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function removalKinds(): array
    {
        return [
            'members' => ['members', 'Player-1-0A'],
            'leavers' => ['leavers', 'Player-1-0A'],
            'bans' => ['bans', 'Player-1-0A'],
            'alt groups' => ['altGroups', '12'],
        ];
    }

    #[Test]
    #[Group('contract')]
    public function it_fails_on_unknown_fields(): void
    {
        $attributes = new ReflectionClass(CommitSyncRequest::class)->getAttributes(FailOnUnknownFields::class);

        $this->assertCount(1, $attributes);
        $this->assertTrue($attributes[0]->newInstance()->value);
    }

    // ==================== ranks ====================

    #[Test]
    public function ranks_is_null_when_the_body_has_no_ranks(): void
    {
        $this->assertNull($this->validatedRequest([])->ranks());
    }

    #[Test]
    public function ranks_reindexes_the_validated_ranks_from_zero(): void
    {
        $ranks = $this->validatedRequest(['ranks' => [3 => 'Guild Master', 7 => 'Officer']])->ranks();

        $this->assertSame(['Guild Master', 'Officer'], $ranks);
    }

    // ==================== removed ====================

    #[Test]
    #[Group('happy-path')]
    public function removed_hydrates_the_validated_removals(): void
    {
        $removed = $this->validatedRequest($this->body())->removed();

        $this->assertEquals(
            new RosterRemovalsData(
                members: ['Player-1-0A'],
                leavers: ['Player-1-0B'],
                alt_groups: ['12'],
                bans: ['Player-1-0C'],
            ),
            $removed,
        );
    }

    #[Test]
    public function removed_is_empty_when_the_body_has_no_removals(): void
    {
        $this->assertEquals(new RosterRemovalsData, $this->validatedRequest([])->removed());
    }

    // ==================== helpers ====================

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        return [
            'ranks' => ['Guild Master', 'Officer', 'Raider'],
            'removed' => [
                'members' => ['Player-1-0A'],
                'leavers' => ['Player-1-0B'],
                'altGroups' => ['12'],
                'bans' => ['Player-1-0C'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function errorsFor(array $body): MessageBag
    {
        try {
            $this->validatedRequest($body);
        } catch (ValidationException $exception) {
            return $exception->validator->errors();
        }

        return new MessageBag;
    }

    /**
     * Build the request around the body for a user who may write to the sync and update bans, and run its full validation, throwing when it fails.
     *
     * @param  array<string, mixed>  $body
     */
    private function validatedRequest(array $body): CommitSyncRequest
    {
        $request = $this->makeRequest($body, mayWriteSync: true, mayUpdateBans: true);
        $request->setContainer($this->app)->setRedirector($this->app->make(Redirector::class));

        $request->validateResolved();

        return $request;
    }

    /**
     * Build a commit request for a sync, with a user holding the given abilities, or never asked about updating bans when null.
     *
     * @param  array<string, mixed>  $body
     */
    private function makeRequest(array $body, bool $mayWriteSync, ?bool $mayUpdateBans = null): CommitSyncRequest
    {
        $sync = new Upload;

        $user = Mockery::mock(User::class, function (MockInterface $user) use ($sync, $mayWriteSync, $mayUpdateBans): void {
            $user->shouldReceive('can')->with('writeSync', [$sync, null])->andReturn($mayWriteSync);

            if ($mayUpdateBans === null) {
                $user->shouldNotReceive('can')->with('update', Ban::class);
            } else {
                $user->shouldReceive('can')->with('update', Ban::class)->andReturn($mayUpdateBans);
            }
        });

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('sync', null)->andReturn($sync);

        $request = CommitSyncRequest::create('/', 'POST', $body);
        $request->setUserResolver(fn (): User => $user);
        $request->setRouteResolver(fn (): Route => $route);

        return $request;
    }
}
