<?php

namespace Tests\Unit\Http\Requests\GuildRosterManager;

use App\Data\GuildRosterManager\MemberData;
use App\Data\GuildRosterManager\MembershipEventData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Http\Requests\GuildRosterManager\Concerns\ValidatesCharacterRows;
use App\Http\Requests\GuildRosterManager\StoreMemberBatchRequest;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('characters')]
class StoreMemberBatchRequestTest extends TestCase
{
    #[Test]
    public function kind_is_member(): void
    {
        $this->assertSame(RosterEntryKind::Member, new StoreMemberBatchRequest()->kind());
    }

    #[Test]
    #[Group('contract')]
    public function it_validates_its_rows_as_characters(): void
    {
        $this->assertContains(ValidatesCharacterRows::class, new ReflectionClass(StoreMemberBatchRequest::class)->getTraitNames());
    }

    #[Test]
    #[Group('happy-path')]
    public function rows_hydrates_each_validated_row_as_member_data_with_its_alt_group(): void
    {
        $rows = $this->validatedRequest([$this->row()])->rows();

        $this->assertEquals(
            [new MemberData(
                guid: 'Player-1-0A',
                name: 'Stillhere',
                rank_index: 3,
                level: 60,
                class: 'MAGE',
                race: 'Undead',
                alt_group: 'group-1',
                history: [new MembershipEventData('joined', '2026-01-02')],
                epoch: 1700000000,
            )],
            $rows,
        );
    }

    #[Test]
    #[Group('validation')]
    public function it_accepts_a_null_alt_group(): void
    {
        $rows = $this->validatedRequest([$this->row(['altGroup' => null])])->rows();

        $this->assertNull($rows[0]->alt_group);
    }

    #[Test]
    #[Group('validation')]
    public function it_accepts_a_row_that_omits_the_alt_group(): void
    {
        $row = $this->row();
        unset($row['altGroup']);

        $rows = $this->validatedRequest([$row])->rows();

        $this->assertNull($rows[0]->alt_group);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_an_alt_group_over_32_characters(): void
    {
        $errors = $this->errorsFor([$this->row(['altGroup' => str_repeat('a', 33)])]);

        $this->assertSame(['rows.0.altGroup' => ['The rows.0.altGroup field must not be greater than 32 characters.']], $errors->toArray());
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
            'name' => 'Stillhere',
            'rankIndex' => 3,
            'level' => 60,
            'class' => 'MAGE',
            'race' => 'Undead',
            'altGroup' => 'group-1',
            'history' => [['type' => 'joined', 'date' => '2026-01-02']],
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
    private function validatedRequest(array $rows): StoreMemberBatchRequest
    {
        $sync = new Upload;

        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->with('writeSync', [$sync, null])->andReturn(true);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('sync', null)->andReturn($sync);

        $request = StoreMemberBatchRequest::create('/', 'POST', ['rows' => $rows]);
        $request->setUserResolver(fn (): User => $user);
        $request->setRouteResolver(fn (): Route => $route);
        $request->setContainer($this->app)->setRedirector($this->app->make(Redirector::class));

        $request->validateResolved();

        return $request;
    }
}
