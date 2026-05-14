<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\BlizzardMediaRequest;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlizzardMediaRequestTest extends TestCase
{
    private function makeRequest(array $params = [], ?User $user = null): BlizzardMediaRequest
    {
        $request = BlizzardMediaRequest::create('/', 'GET', $params);

        $user ??= User::factory()->make();
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    // ==================== authorization ====================

    #[Test]
    public function authorize_allows_authenticated_users(): void
    {
        $request = $this->makeRequest();

        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function authorize_denies_unauthenticated_requests(): void
    {
        $request = BlizzardMediaRequest::create('/', 'GET');
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }

    // ==================== rules ====================

    #[Test]
    public function rules_name_is_nullable_string(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertContains('nullable', $rules['name']);
        $this->assertContains('string', $rules['name']);
    }

    #[Test]
    public function rules_tags_is_nullable_array(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('tags', $rules);
        $this->assertContains('nullable', $rules['tags']);
        $this->assertContains('array', $rules['tags']);
    }

    #[Test]
    public function rules_tags_items_must_be_valid_media_tag_string(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('tags.*', $rules);
        $this->assertContains('string', $rules['tags.*']);
        $this->assertContains('in:item,spell,playable-class', $rules['tags.*']);
    }

    #[Test]
    public function rules_page_is_nullable_integer_with_minimum_of_one(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('page', $rules);
        $this->assertContains('nullable', $rules['page']);
        $this->assertContains('integer', $rules['page']);
        $this->assertContains('min:1', $rules['page']);
    }

    // ==================== prepareForValidation ====================

    #[Test]
    public function prepare_for_validation_lowercases_name(): void
    {
        $request = $this->makeRequest(['name' => 'FrostBolt']);

        $reflection = new \ReflectionMethod($request, 'prepareForValidation');
        $reflection->invoke($request);

        $this->assertSame('frostbolt', $request->input('name'));
    }

    #[Test]
    public function prepare_for_validation_leaves_missing_name_untouched(): void
    {
        $request = $this->makeRequest([]);

        $reflection = new \ReflectionMethod($request, 'prepareForValidation');
        $reflection->invoke($request);

        $this->assertNull($request->input('name'));
    }
}
