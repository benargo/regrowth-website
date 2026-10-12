<?php

namespace Tests\Unit\Http\Requests;

use App\Enums\PlannedAbsenceReason;
use App\Http\Requests\StorePlannedAbsenceRequest;
use App\Models\PlannedAbsence;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class StorePlannedAbsenceRequestTest extends TestCase
{
    private function makeRequest(array $data = []): StorePlannedAbsenceRequest
    {
        return StorePlannedAbsenceRequest::create('/', 'POST', $data);
    }

    // ==================== rules ====================

    #[Test]
    public function rules_require_a_released_game_version(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertContains('required', $rules['game_version']);
        $this->assertContains('integer', $rules['game_version']);
        $this->assertTrue(collect($rules['game_version'])->contains(fn ($rule) => $rule instanceof Exists));
    }

    #[Test]
    public function rules_require_the_character_by_id(): void
    {
        $rules = $this->makeRequest(['character' => 'Thrall'])->rules();

        $this->assertContains('required', $rules['character']);
        $this->assertContains('integer', $rules['character']);
        $this->assertContains('min:1', $rules['character']);
        $this->assertTrue(collect($rules['character'])->contains(fn ($rule) => $rule instanceof Exists));
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
    public function rules_require_a_reason_from_the_enum(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertContains('required', $rules['reason']);
        $this->assertTrue(collect($rules['reason'])->contains(fn ($rule) => $rule instanceof Enum));
    }

    #[Test]
    public function rules_make_notes_optional_for_a_listed_reason(): void
    {
        $validator = Validator::make(
            ['reason' => PlannedAbsenceReason::Holiday->value, 'notes' => null],
            Arr::only($this->makeRequest(['reason' => PlannedAbsenceReason::Holiday->value])->rules(), ['reason', 'notes']),
        );

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function rules_require_notes_for_other(): void
    {
        $validator = Validator::make(
            ['reason' => PlannedAbsenceReason::Other->value, 'notes' => null],
            Arr::only($this->makeRequest(['reason' => PlannedAbsenceReason::Other->value])->rules(), ['reason', 'notes']),
        );

        $this->assertSame(['notes'], $validator->errors()->keys());
    }
}
