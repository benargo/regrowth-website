<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\CreateEventAssignmentRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class CreateEventAssignmentRequestTest extends TestCase
{
    // ==================== rules ====================

    #[Test]
    public function rules_boss_id_is_nullable_integer_that_must_exist(): void
    {
        $rules = (new CreateEventAssignmentRequest)->rules();

        $this->assertArrayHasKey('boss_id', $rules);
        $this->assertContains('nullable', $rules['boss_id']);
        $this->assertContains('integer', $rules['boss_id']);
        $this->assertContains('exists:bosses,id', $rules['boss_id']);
    }

    #[Test]
    public function rules_group_id_is_nullable_integer_that_must_exist(): void
    {
        $rules = (new CreateEventAssignmentRequest)->rules();

        $this->assertArrayHasKey('group_id', $rules);
        $this->assertContains('nullable', $rules['group_id']);
        $this->assertContains('integer', $rules['group_id']);
        $this->assertContains('exists:event_assignment_groups,id', $rules['group_id']);
    }

    #[Test]
    public function rules_sort_order_is_optional_non_negative_integer(): void
    {
        $rules = (new CreateEventAssignmentRequest)->rules();

        $this->assertArrayHasKey('sort_order', $rules);
        $this->assertContains('sometimes', $rules['sort_order']);
        $this->assertContains('integer', $rules['sort_order']);
        $this->assertContains('min:0', $rules['sort_order']);
        $this->assertNotContains('required', $rules['sort_order']);
    }

    #[Test]
    public function rules_left_type_is_nullable_string(): void
    {
        $rules = (new CreateEventAssignmentRequest)->rules();

        $this->assertArrayHasKey('left_type', $rules);
        $this->assertContains('nullable', $rules['left_type']);
        $this->assertContains('string', $rules['left_type']);
        $this->assertContains('max:255', $rules['left_type']);
    }

    #[Test]
    public function rules_left_value_is_nullable_string(): void
    {
        $rules = (new CreateEventAssignmentRequest)->rules();

        $this->assertArrayHasKey('left_value', $rules);
        $this->assertContains('nullable', $rules['left_value']);
        $this->assertContains('string', $rules['left_value']);
    }

    #[Test]
    public function rules_right_type_is_nullable_string(): void
    {
        $rules = (new CreateEventAssignmentRequest)->rules();

        $this->assertArrayHasKey('right_type', $rules);
        $this->assertContains('nullable', $rules['right_type']);
        $this->assertContains('string', $rules['right_type']);
        $this->assertContains('max:255', $rules['right_type']);
    }

    #[Test]
    public function rules_right_value_is_nullable_string(): void
    {
        $rules = (new CreateEventAssignmentRequest)->rules();

        $this->assertArrayHasKey('right_value', $rules);
        $this->assertContains('nullable', $rules['right_value']);
        $this->assertContains('string', $rules['right_value']);
    }
}
