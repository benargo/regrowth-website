<?php

namespace Tests\Unit\Http\Requests\Concerns;

use App\Contracts\Models\EditLockable;
use App\Http\Requests\Concerns\ChecksEditLock;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class ChecksEditLockTest extends TestCase
{
    #[Test]
    #[Group('validation')]
    public function it_adds_an_edit_lock_error_when_another_user_holds_the_lock(): void
    {
        $user = new User;
        $record = $this->lockable($user, lockedByAnother: true);

        $validator = $this->runCheck($this->makeRequest('record', $record, $user), 'record', 'widget');

        $this->assertSame(
            ['Someone else is editing this widget. Your change was not saved.'],
            $validator->errors()->get('edit_lock'),
        );
    }

    #[Test]
    #[Group('happy-path')]
    public function it_adds_no_error_when_no_one_else_holds_the_lock(): void
    {
        $user = new User;
        $record = $this->lockable($user, lockedByAnother: false);

        $validator = $this->runCheck($this->makeRequest('record', $record, $user), 'record', 'widget');

        $this->assertFalse($validator->errors()->has('edit_lock'));
    }

    #[Test]
    public function it_checks_the_record_bound_to_the_named_route_parameter(): void
    {
        $user = new User;
        $record = $this->lockable($user, lockedByAnother: true);

        $validator = $this->runCheck($this->makeRequest('gameVersion', $record, $user), 'gameVersion', 'game version');

        $this->assertSame(
            ['Someone else is editing this game version. Your change was not saved.'],
            $validator->errors()->get('edit_lock'),
        );
    }

    // ==================== helpers ====================

    private function lockable(User $user, bool $lockedByAnother): EditLockable
    {
        $record = Mockery::mock(EditLockable::class);
        $record->shouldReceive('isLockedForEditingBy')->once()->with($user)->andReturn($lockedByAnother);

        return $record;
    }

    private function makeRequest(string $routeParameter, EditLockable $record, User $user): FormRequest
    {
        $request = new class extends FormRequest
        {
            use ChecksEditLock;

            public function check(string $routeParameter, string $recordName): Closure
            {
                return $this->editLockCheck($routeParameter, $recordName);
            }
        };

        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(function () use ($routeParameter, $record) {
            $route = Mockery::mock();
            $route->shouldReceive('parameter')->with($routeParameter, null)->andReturn($record);

            return $route;
        });

        return $request;
    }

    private function runCheck(FormRequest $request, string $routeParameter, string $recordName): Validator
    {
        $validator = ValidatorFacade::make([], []);

        $request->check($routeParameter, $recordName)($validator);

        return $validator;
    }
}
