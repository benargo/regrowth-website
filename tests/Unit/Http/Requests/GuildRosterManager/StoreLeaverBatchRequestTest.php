<?php

namespace Tests\Unit\Http\Requests\GuildRosterManager;

use App\Data\GuildRosterManager\LeaverData;
use App\Data\GuildRosterManager\MembershipEventData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Http\Requests\GuildRosterManager\Concerns\ValidatesCharacterRows;
use App\Http\Requests\GuildRosterManager\StoreLeaverBatchRequest;
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
class StoreLeaverBatchRequestTest extends TestCase
{
    #[Test]
    public function kind_is_leaver(): void
    {
        $this->assertSame(RosterEntryKind::Leaver, new StoreLeaverBatchRequest()->kind());
    }

    #[Test]
    #[Group('contract')]
    public function it_validates_its_rows_as_characters(): void
    {
        $this->assertContains(ValidatesCharacterRows::class, new ReflectionClass(StoreLeaverBatchRequest::class)->getTraitNames());
    }

    #[Test]
    #[Group('happy-path')]
    public function rows_hydrates_each_validated_row_as_leaver_data_with_its_history(): void
    {
        $rows = $this->validatedRequest([$this->row()])->rows();

        $this->assertEquals(
            [new LeaverData(
                guid: 'Player-1-0A',
                name: 'Leftalready',
                rank_index: 5,
                level: 60,
                class: 'WARRIOR',
                race: 'Orc',
                history: [new MembershipEventData('joined', '2026-01-02'), new MembershipEventData('left', '2026-03-04')],
                epoch: 1700000000,
            )],
            $rows,
        );
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_an_alt_group_on_a_leaver_row(): void
    {
        $errors = $this->errorsFor([$this->row(['altGroup' => 'group-1'])]);

        $this->assertSame(['rows.0.altGroup'], $errors->keys());
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
            'name' => 'Leftalready',
            'rankIndex' => 5,
            'level' => 60,
            'class' => 'WARRIOR',
            'race' => 'Orc',
            'history' => [
                ['type' => 'joined', 'date' => '2026-01-02'],
                ['type' => 'left', 'date' => '2026-03-04'],
            ],
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
    private function validatedRequest(array $rows): StoreLeaverBatchRequest
    {
        $sync = new Upload;

        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->with('writeSync', [$sync, null])->andReturn(true);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('sync', null)->andReturn($sync);

        $request = StoreLeaverBatchRequest::create('/', 'POST', ['rows' => $rows]);
        $request->setUserResolver(fn (): User => $user);
        $request->setRouteResolver(fn (): Route => $route);
        $request->setContainer($this->app)->setRedirector($this->app->make(Redirector::class));

        $request->validateResolved();

        return $request;
    }
}
