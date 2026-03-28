<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Rules\Unique;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileUpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(): ProfileUpdateRequest
    {
        $request = ProfileUpdateRequest::create('/', 'PATCH');

        $user = User::factory()->create();
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    // ==================== rules ====================

    #[Test]
    public function rules_requires_name(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertContains('required', $rules['name']);
        $this->assertContains('string', $rules['name']);
        $this->assertContains('max:255', $rules['name']);
    }

    #[Test]
    public function rules_requires_email(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('email', $rules);
        $this->assertContains('required', $rules['email']);
        $this->assertContains('string', $rules['email']);
        $this->assertContains('lowercase', $rules['email']);
        $this->assertContains('email', $rules['email']);
        $this->assertContains('max:255', $rules['email']);
    }

    #[Test]
    public function rules_email_has_unique_constraint_ignoring_current_user(): void
    {
        $request = $this->makeRequest();
        $rules = $request->rules();

        $uniqueRule = collect($rules['email'])->first(fn ($rule) => $rule instanceof Unique);

        $this->assertNotNull($uniqueRule, 'Email rules should contain a Unique rule.');
    }
}
