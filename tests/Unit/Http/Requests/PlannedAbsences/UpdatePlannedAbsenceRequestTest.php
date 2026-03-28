<?php

namespace Tests\Unit\Http\Requests\PlannedAbsences;

use App\Http\Requests\PlannedAbsences\UpdatePlannedAbsenceRequest;
use App\Models\PlannedAbsence;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rules\Exists;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdatePlannedAbsenceRequestTest extends TestCase
{
    private function makeRequest(array $data = []): UpdatePlannedAbsenceRequest
    {
        return UpdatePlannedAbsenceRequest::create('/', 'PATCH', $data);
    }

    // ==================== rules ====================

    #[Test]
    public function rules_character_uses_integer_and_exists_rules(): void
    {
        $rules = $this->makeRequest(['character' => '5'])->rules();

        $this->assertArrayHasKey('character', $rules);
        $this->assertContains('sometimes', $rules['character']);
        $this->assertContains('integer', $rules['character']);
        $this->assertContains('min:1', $rules['character']);
        $this->assertTrue(collect($rules['character'])->contains(fn ($r) => $r instanceof Exists));
    }

    #[Test]
    public function rules_character_is_sometimes_not_required(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('character', $rules);
        $this->assertContains('sometimes', $rules['character']);
        $this->assertNotContains('required', $rules['character']);
    }

    #[Test]
    public function rules_user_is_sometimes_optional_nullable_string(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('user', $rules);
        $this->assertContains('sometimes', $rules['user']);
        $this->assertContains('nullable', $rules['user']);
        $this->assertContains('string', $rules['user']);
        $this->assertNotContains('required', $rules['user']);
    }

    #[Test]
    public function rules_start_date_is_sometimes_not_required(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('start_date', $rules);
        $this->assertContains('sometimes', $rules['start_date']);
        $this->assertContains('date', $rules['start_date']);
        $this->assertNotContains('required', $rules['start_date']);
    }

    #[Test]
    public function rules_start_date_includes_after_or_equal_today_when_user_cannot_backdate(): void
    {
        $absence = \Mockery::mock(PlannedAbsence::class);
        $absence->shouldReceive('getAttribute')->with('start_date')->andReturn(null);

        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('createBackdated', PlannedAbsence::class)->andReturn(false);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);
        $route = \Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('plannedAbsence', null)->andReturn($absence);
        $request->setRouteResolver(fn () => $route);

        $rules = $request->rules();

        $this->assertContains('after_or_equal:today', $rules['start_date']);
    }

    #[Test]
    public function rules_start_date_excludes_after_or_equal_today_when_user_can_backdate(): void
    {
        $absence = \Mockery::mock(PlannedAbsence::class);
        $absence->shouldReceive('getAttribute')->with('start_date')->andReturn(null);

        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('createBackdated', PlannedAbsence::class)->andReturn(true);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);
        $route = \Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('plannedAbsence', null)->andReturn($absence);
        $request->setRouteResolver(fn () => $route);

        $rules = $request->rules();

        $this->assertNotContains('after_or_equal:today', $rules['start_date']);
    }

    #[Test]
    public function rules_end_date_is_sometimes_nullable_date(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('end_date', $rules);
        $this->assertContains('sometimes', $rules['end_date']);
        $this->assertContains('nullable', $rules['end_date']);
        $this->assertContains('date', $rules['end_date']);
    }

    #[Test]
    public function rules_end_date_includes_after_clause_when_start_date_is_provided(): void
    {
        $rules = $this->makeRequest(['start_date' => '2026-04-01'])->rules();

        $this->assertArrayHasKey('end_date', $rules);
        $this->assertContains('after:2026-04-01', $rules['end_date']);
    }

    #[Test]
    public function rules_reason_is_sometimes_string(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('reason', $rules);
        $this->assertContains('sometimes', $rules['reason']);
        $this->assertContains('string', $rules['reason']);
        $this->assertNotContains('required', $rules['reason']);
    }

    // ==================== authorize ====================

    #[Test]
    public function authorize_returns_false_when_user_cannot_update_planned_absence(): void
    {
        $absence = \Mockery::mock(PlannedAbsence::class);

        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('update', $absence)->andReturn(false);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);
        $route = \Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('plannedAbsence', null)->andReturn($absence);
        $request->setRouteResolver(fn () => $route);

        $this->assertFalse($request->authorize());
    }

    #[Test]
    public function authorize_returns_true_when_user_can_update_and_no_character_is_passed(): void
    {
        $absence = \Mockery::mock(PlannedAbsence::class);

        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('update', $absence)->andReturn(true);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);
        $route = \Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('plannedAbsence', null)->andReturn($absence);
        $request->setRouteResolver(fn () => $route);

        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function authorize_returns_false_when_character_is_passed_and_user_lacks_update_permission(): void
    {
        $absence = \Mockery::mock(PlannedAbsence::class);

        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('update', $absence)->andReturn(true);
        $user->shouldReceive('hasPermissionViaDiscordRoles')->with('update-planned-absences')->andReturn(false);

        $request = $this->makeRequest(['character' => '5']);
        $request->setUserResolver(fn () => $user);
        $route = \Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('plannedAbsence', null)->andReturn($absence);
        $request->setRouteResolver(fn () => $route);

        $this->assertFalse($request->authorize());
    }

    #[Test]
    public function authorize_returns_true_when_character_is_passed_and_user_has_update_permission(): void
    {
        $absence = \Mockery::mock(PlannedAbsence::class);

        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('update', $absence)->andReturn(true);
        $user->shouldReceive('hasPermissionViaDiscordRoles')->with('update-planned-absences')->andReturn(true);

        $request = $this->makeRequest(['character' => '5']);
        $request->setUserResolver(fn () => $user);
        $route = \Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('plannedAbsence', null)->andReturn($absence);
        $request->setRouteResolver(fn () => $route);

        $this->assertTrue($request->authorize());
    }
}
