<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\CreateSpellRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Rules\Enum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateSpellRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(array $params = []): CreateSpellRequest
    {
        $request = CreateSpellRequest::create('/', 'POST', $params);

        $user = User::factory()->create();
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    // ==================== rules ====================

    #[Test]
    public function rules_name_is_required_string_max_255(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertContains('required', $rules['name']);
        $this->assertContains('string', $rules['name']);
        $this->assertContains('max:255', $rules['name']);
    }

    #[Test]
    public function rules_type_is_required_enum_affect_type(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('type', $rules);
        $this->assertContains('required', $rules['type']);

        $enumRule = collect($rules['type'])->first(fn ($rule) => $rule instanceof Enum);
        $this->assertNotNull($enumRule, 'Type rules should contain an Enum rule.');
    }
}
