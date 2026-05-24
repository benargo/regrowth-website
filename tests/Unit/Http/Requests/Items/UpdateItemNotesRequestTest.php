<?php

namespace Tests\Unit\Http\Requests\Items;

use App\Http\Requests\Items\UpdateItemNotesRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateItemNotesRequestTest extends TestCase
{
    private function makeRequest(array $data = []): UpdateItemNotesRequest
    {
        return UpdateItemNotesRequest::create('/', 'PATCH', $data);
    }

    // ==================== rules ====================

    #[Test]
    public function rules_notes_is_nullable_string_with_max_5000(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('notes', $rules);
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
}
