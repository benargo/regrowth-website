<?php

namespace Tests\Unit\Http\Requests\GuildRosterManager;

use App\Data\GuildRosterManager\AltGroupData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Http\Requests\GuildRosterManager\StoreAltGroupBatchRequest;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('characters')]
class StoreAltGroupBatchRequestTest extends TestCase
{
    #[Test]
    public function kind_is_alt_group(): void
    {
        $this->assertSame(RosterEntryKind::AltGroup, new StoreAltGroupBatchRequest()->kind());
    }

    #[Test]
    #[Group('happy-path')]
    public function rows_hydrates_each_validated_row_as_alt_group_data(): void
    {
        $rows = $this->validatedRequest([$this->row(), $this->row(['id' => 'group-2', 'main' => null, 'members' => []])])->rows();

        $this->assertEquals(
            [
                new AltGroupData('group-1', 'Mainchar', ['Player-1-0A', 'Player-1-0B'], 1700000000),
                new AltGroupData('group-2', null, [], 1700000000),
            ],
            $rows,
        );
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_the_id_members_and_epoch_of_a_row(): void
    {
        $errors = $this->errorsFor([['main' => 'Mainchar']]);

        $this->assertSame(['rows.0.id', 'rows.0.members', 'rows.0.epoch'], $errors->keys());
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
            'id over 32 characters' => ['id', str_repeat('a', 33), 'The rows.0.id field must not be greater than 32 characters.'],
            'main over 64 characters' => ['main', str_repeat('a', 65), 'The rows.0.main field must not be greater than 64 characters.'],
            'members not an array' => ['members', 'Player-1-0A', 'The rows.0.members field must be an array.'],
            'over 50 members' => ['members', array_map(fn (int $index): string => sprintf('Player-1-%X', $index), range(1, 51)), 'The rows.0.members field must not have more than 50 items.'],
            'negative epoch' => ['epoch', -1, 'The rows.0.epoch field must be at least 0.'],
        ];
    }

    #[Test]
    #[Group('validation')]
    #[TestWith(['Player-1-0a'])]
    #[TestWith(['Creature-1-0A'])]
    #[TestWith(['Mainchar'])]
    public function it_rejects_a_member_that_is_not_a_player_guid(string $member): void
    {
        $errors = $this->errorsFor([$this->row(['members' => ['Player-1-0A', $member]])]);

        $this->assertSame(['rows.0.members.1' => ['The rows.0.members.1 field format is invalid.']], $errors->toArray());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_two_rows_with_the_same_id(): void
    {
        $errors = $this->errorsFor([$this->row(), $this->row()]);

        $this->assertSame(['rows.0.id', 'rows.1.id'], $errors->keys());
        $this->assertSame(['The rows.0.id field has a duplicate value.'], $errors->get('rows.0.id'));
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_member_field_on_an_alt_group_row(): void
    {
        $errors = $this->errorsFor([$this->row(['guid' => 'Player-1-0A'])]);

        $this->assertSame(['rows.0.guid'], $errors->keys());
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return [
            'id' => 'group-1',
            'main' => 'Mainchar',
            'members' => ['Player-1-0A', 'Player-1-0B'],
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
     * Build the request around the rows for a user who may write to the sync, and run its full validation, throwing when it fails.
     *
     * @param  array<int, mixed>  $rows
     */
    private function validatedRequest(array $rows): StoreAltGroupBatchRequest
    {
        $sync = new Upload;

        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->with('writeSync', [$sync, null])->andReturn(true);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('sync', null)->andReturn($sync);

        $request = StoreAltGroupBatchRequest::create('/', 'POST', ['rows' => $rows]);
        $request->setUserResolver(fn (): User => $user);
        $request->setRouteResolver(fn (): Route => $route);
        $request->setContainer($this->app)->setRedirector($this->app->make(Redirector::class));

        $request->validateResolved();

        return $request;
    }
}
