<?php

namespace Tests\Unit\Http\Requests\Items;

use App\Http\Requests\Items\UpdateItemRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class UpdateItemRequestTest extends TestCase
{
    private function makeRequest(array $data = []): UpdateItemRequest
    {
        return UpdateItemRequest::create('/', 'PATCH', $data);
    }

    // ==================== notes rules ====================

    #[Test]
    public function rules_notes_is_sometimes_nullable_string_with_max_5000(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('notes', $rules);
        $this->assertContains('sometimes', $rules['notes']);
        $this->assertContains('nullable', $rules['notes']);
        $this->assertContains('string', $rules['notes']);
        $this->assertContains('max:5000', $rules['notes']);
    }

    #[Test]
    public function rules_notes_is_not_required(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertNotContains('required', $rules['notes']);
    }

    // ==================== priorities rules ====================

    #[Test]
    public function rules_priorities_is_sometimes_array(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('priorities', $rules);
        $this->assertContains('sometimes', $rules['priorities']);
        $this->assertContains('array', $rules['priorities']);
    }

    #[Test]
    public function rules_priorities_is_not_required(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertNotContains('required', $rules['priorities']);
        $this->assertNotContains('present', $rules['priorities']);
    }

    #[Test]
    public function rules_priorities_priority_id_requires_integer_and_exists(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('priorities.*.priority_id', $rules);
        $this->assertContains('required', $rules['priorities.*.priority_id']);
        $this->assertContains('integer', $rules['priorities.*.priority_id']);
        $this->assertContains('exists:loot_priorities,id', $rules['priorities.*.priority_id']);
    }

    #[Test]
    public function rules_priorities_weight_requires_integer_with_min_zero(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('priorities.*.weight', $rules);
        $this->assertContains('required', $rules['priorities.*.weight']);
        $this->assertContains('integer', $rules['priorities.*.weight']);
        $this->assertContains('min:0', $rules['priorities.*.weight']);
    }
}
