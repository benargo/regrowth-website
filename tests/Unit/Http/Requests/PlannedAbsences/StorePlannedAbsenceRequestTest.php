<?php

namespace Tests\Unit\Http\Requests\PlannedAbsences;

use App\Http\Requests\PlannedAbsences\StorePlannedAbsenceRequest;
use App\Models\PlannedAbsence;
use Illuminate\Validation\Rules\Exists;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StorePlannedAbsenceRequestTest extends TestCase
{
    private function makeRequest(array $data = []): StorePlannedAbsenceRequest
    {
        return StorePlannedAbsenceRequest::create('/', 'POST', $data);
    }

    // ==================== rules ====================

    #[Test]
    public function rules_character_uses_integer_rules_when_value_is_numeric(): void
    {
        $rules = $this->makeRequest(['character' => '5'])->rules();

        $this->assertArrayHasKey('character', $rules);
        $this->assertContains('required', $rules['character']);
        $this->assertContains('integer', $rules['character']);
        $this->assertContains('min:1', $rules['character']);
        $this->assertTrue(collect($rules['character'])->contains(fn ($r) => $r instanceof Exists));
    }

    #[Test]
    public function rules_character_uses_string_rules_when_value_is_not_numeric(): void
    {
        $rules = $this->makeRequest(['character' => 'Thrall'])->rules();

        $this->assertArrayHasKey('character', $rules);
        $this->assertContains('required', $rules['character']);
        $this->assertContains('string', $rules['character']);
        $this->assertContains('max:11', $rules['character']);
        $this->assertContains('regex:/^[^\d\s]+$/u', $rules['character']);
    }

    #[Test]
    public function rules_character_uses_string_rules_when_no_value_provided(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('character', $rules);
        $this->assertContains('string', $rules['character']);
    }

    #[Test]
    public function rules_requires_start_date(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('start_date', $rules);
        $this->assertContains('required', $rules['start_date']);
        $this->assertContains('date', $rules['start_date']);
    }

    #[Test]
    public function rules_start_date_includes_after_or_equal_today_when_user_cannot_backdate(): void
    {
        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('createBackdated', PlannedAbsence::class)->andReturn(false);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);

        $rules = $request->rules();

        $this->assertContains('after_or_equal:today', $rules['start_date']);
    }

    #[Test]
    public function rules_start_date_excludes_after_or_equal_today_when_user_can_backdate(): void
    {
        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('createBackdated', PlannedAbsence::class)->andReturn(true);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);

        $rules = $request->rules();

        $this->assertNotContains('after_or_equal:today', $rules['start_date']);
    }

    #[Test]
    public function rules_end_date_is_nullable_date_after_start_date(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('end_date', $rules);
        $this->assertContains('nullable', $rules['end_date']);
        $this->assertContains('date', $rules['end_date']);
        $this->assertContains('after:start_date', $rules['end_date']);
    }

    #[Test]
    public function rules_user_is_optional_nullable_string(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('user', $rules);
        $this->assertContains('sometimes', $rules['user']);
        $this->assertContains('nullable', $rules['user']);
        $this->assertContains('string', $rules['user']);
        $this->assertNotContains('required', $rules['user']);
    }

    #[Test]
    public function rules_requires_reason_as_string(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('reason', $rules);
        $this->assertContains('required', $rules['reason']);
        $this->assertContains('string', $rules['reason']);
    }

    // ==================== authorize ====================

    #[Test]
    public function authorize_returns_true_when_user_can_create_planned_absence(): void
    {
        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('create', PlannedAbsence::class)->andReturn(true);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function authorize_returns_false_when_user_cannot_create_planned_absence(): void
    {
        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('create', PlannedAbsence::class)->andReturn(false);

        $request = $this->makeRequest();
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }
}
