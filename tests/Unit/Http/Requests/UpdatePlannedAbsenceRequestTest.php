<?php

namespace Tests\Unit\Http\Requests;

use App\Enums\PlannedAbsenceReason;
use App\Http\Requests\UpdatePlannedAbsenceRequest;
use App\Models\PlannedAbsence;
use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
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
    public function rules_require_a_released_game_version_with_a_character(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertContains('required_with:character', $rules['game_version']);
        $this->assertContains('integer', $rules['game_version']);
        $this->assertTrue(collect($rules['game_version'])->contains(fn ($rule) => $rule instanceof Closure));
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
        $user->shouldReceive('can')->with('backdate', PlannedAbsence::class)->andReturn(false);

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
        $user->shouldReceive('can')->with('backdate', PlannedAbsence::class)->andReturn(true);

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
    public function rules_end_date_includes_after_or_equal_clause_when_start_date_is_provided(): void
    {
        $rules = $this->makeRequest(['start_date' => '2026-04-01'])->rules();

        $this->assertArrayHasKey('end_date', $rules);
        $this->assertContains('after_or_equal:2026-04-01', $rules['end_date']);
    }

    #[Test]
    public function rules_reason_is_sometimes_an_enum(): void
    {
        $rules = $this->makeRequestFor(new PlannedAbsence, [])->rules();

        $this->assertContains('sometimes', $rules['reason']);
        $this->assertTrue(collect($rules['reason'])->contains(fn ($rule) => $rule instanceof Enum));
    }

    #[Test]
    public function update_keeps_other_when_notes_are_already_stored(): void
    {
        $absence = new PlannedAbsence(['reason' => PlannedAbsenceReason::Other, 'notes' => 'Told Thrall.']);

        $this->assertSame([], $this->notesErrors($absence, ['start_date' => today()->addDay()->format('Y-m-d')]));
    }

    #[Test]
    public function rules_require_notes_when_switching_to_other_with_no_stored_notes(): void
    {
        $absence = new PlannedAbsence(['reason' => PlannedAbsenceReason::Holiday, 'notes' => null]);

        $this->assertSame(['notes'], $this->notesErrors($absence, ['reason' => PlannedAbsenceReason::Other->value]));
    }

    #[Test]
    public function rules_reject_clearing_notes_on_an_other_absence(): void
    {
        $absence = new PlannedAbsence(['reason' => PlannedAbsenceReason::Other, 'notes' => 'Told Thrall.']);

        $this->assertSame(['notes'], $this->notesErrors($absence, ['notes' => null]));
    }

    #[Test]
    public function rules_allow_clearing_notes_on_a_listed_reason(): void
    {
        $absence = new PlannedAbsence(['reason' => PlannedAbsenceReason::Work, 'notes' => 'Late shift.']);

        $this->assertSame([], $this->notesErrors($absence, ['notes' => null]));
    }

    private function makeRequestFor(PlannedAbsence $plannedAbsence, array $data): UpdatePlannedAbsenceRequest
    {
        $request = UpdatePlannedAbsenceRequest::create('/', 'PATCH', $data);
        $request->setRouteResolver(function () use ($request, $plannedAbsence) {
            $route = new Route('PATCH', '/{plannedAbsence}', []);
            $route->bind($request);
            $route->setParameter('plannedAbsence', $plannedAbsence);

            return $route;
        });

        return $request;
    }

    private function notesErrors(PlannedAbsence $plannedAbsence, array $data): array
    {
        $rules = Arr::only($this->makeRequestFor($plannedAbsence, $data)->rules(), ['reason', 'notes']);

        return Validator::make($data, $rules)->errors()->keys();
    }
}
