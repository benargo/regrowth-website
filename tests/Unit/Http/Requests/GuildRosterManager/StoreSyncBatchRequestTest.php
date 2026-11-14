<?php

namespace Tests\Unit\Http\Requests\GuildRosterManager;

use App\Data\GuildRosterManager\AltGroupData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Http\Requests\GuildRosterManager\StoreSyncBatchRequest;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionMethod;
use Tests\TestCase;

#[Group('characters')]
class StoreSyncBatchRequestTest extends TestCase
{
    // ==================== authorize ====================

    #[Test]
    #[TestWith([true])]
    #[TestWith([false])]
    public function authorize_follows_whether_the_user_may_write_to_the_sync(bool $mayWriteSync): void
    {
        $request = $this->makeRequest(['rows' => [$this->row()]], $mayWriteSync);

        $this->assertSame($mayWriteSync, $request->authorize());
    }

    // ==================== rules ====================

    #[Test]
    #[Group('validation')]
    #[TestWith([null])]
    #[TestWith([[]])]
    public function it_requires_at_least_one_row(?array $rows): void
    {
        $errors = $this->errorsFor(['rows' => $rows]);

        $this->assertSame(['rows' => ['The rows field is required.']], $errors->toArray());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_rows_that_are_not_an_array(): void
    {
        $errors = $this->errorsFor(['rows' => 'group-1']);

        $this->assertSame(['rows' => ['The rows field must be an array.']], $errors->toArray());
    }

    #[Test]
    #[Group('validation')]
    public function it_accepts_250_rows(): void
    {
        $errors = $this->errorsFor(['rows' => array_fill(0, 250, $this->row())]);

        $this->assertSame([], $errors->toArray());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_more_than_250_rows(): void
    {
        $errors = $this->errorsFor(['rows' => array_fill(0, 251, $this->row())]);

        $this->assertSame(['rows' => ['The rows field must not have more than 250 items.']], $errors->toArray());
    }

    #[Test]
    #[Group('validation')]
    public function it_applies_the_row_rules_to_every_row(): void
    {
        $errors = $this->errorsFor(['rows' => [$this->row(), $this->row(['epoch' => 'soon'])]]);

        $this->assertSame(['rows.1.epoch' => ['The rows.1.epoch field must be an integer.']], $errors->toArray());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_row_key_outside_the_row_rules(): void
    {
        $errors = $this->errorsFor(['rows' => [$this->row(['sneaky' => 'x'])]]);

        $this->assertSame(['rows.0.sneaky'], $errors->keys());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_top_level_key_other_than_rows(): void
    {
        $errors = $this->errorsFor(['rows' => [$this->row()], 'sneaky' => 'x']);

        $this->assertSame(['sneaky'], $errors->keys());
    }

    // ==================== rows ====================

    #[Test]
    #[Group('happy-path')]
    public function rows_hydrates_each_validated_row_as_the_kinds_data_class(): void
    {
        $rows = $this->validatedRequest(['rows' => [$this->row(), $this->row(['id' => 'group-2', 'members' => []])]])->rows();

        $this->assertEquals(
            [
                new AltGroupData('group-1', 'Mainchar', ['Player-1-0A'], 1700000000),
                new AltGroupData('group-2', 'Mainchar', [], 1700000000),
            ],
            $rows,
        );
    }

    // ==================== rowsInput ====================

    #[Test]
    public function rows_input_returns_the_raw_rows_before_validation(): void
    {
        $rows = ['not a row', ['id' => 42]];

        $this->assertSame($rows, $this->rowsInput($this->makeRequest(['rows' => $rows], mayWriteSync: true)));
    }

    #[Test]
    #[TestWith([null])]
    #[TestWith(['group-1'])]
    public function rows_input_returns_no_rows_when_rows_is_not_an_array(?string $rows): void
    {
        $this->assertSame([], $this->rowsInput($this->makeRequest(['rows' => $rows], mayWriteSync: true)));
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
            'members' => ['Player-1-0A'],
            'epoch' => 1700000000,
            ...$overrides,
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
     * Build the request around the body for a user who may write to the sync, and run its full validation, throwing when it fails.
     *
     * @param  array<string, mixed>  $body
     */
    private function validatedRequest(array $body): StoreSyncBatchRequest
    {
        $request = $this->makeRequest($body, mayWriteSync: true);
        $request->setContainer($this->app)->setRedirector($this->app->make(Redirector::class));

        $request->validateResolved();

        return $request;
    }

    /**
     * Build a batch request carrying alt group rows under minimal row rules, for a sync and a user who may or may not write to it.
     *
     * @param  array<string, mixed>  $body
     */
    private function makeRequest(array $body, bool $mayWriteSync): StoreSyncBatchRequest
    {
        $prototype = new class extends StoreSyncBatchRequest
        {
            public function kind(): RosterEntryKind
            {
                return RosterEntryKind::AltGroup;
            }

            protected function rowRules(): array
            {
                return [
                    'id' => ['required', 'string'],
                    'main' => ['nullable', 'string'],
                    'members' => ['present', 'array'],
                    'members.*' => ['string'],
                    'epoch' => ['required', 'integer'],
                ];
            }
        };

        $sync = new Upload;

        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->with('writeSync', [$sync, null])->andReturn($mayWriteSync);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('sync', null)->andReturn($sync);

        $request = $prototype::create('/', 'POST', $body);
        $request->setUserResolver(fn (): User => $user);
        $request->setRouteResolver(fn (): Route => $route);

        return $request;
    }

    /**
     * Call the request's protected `rowsInput()`.
     *
     * @return array<array-key, mixed>
     */
    private function rowsInput(StoreSyncBatchRequest $request): array
    {
        return new ReflectionMethod($request, 'rowsInput')->invoke($request);
    }
}
