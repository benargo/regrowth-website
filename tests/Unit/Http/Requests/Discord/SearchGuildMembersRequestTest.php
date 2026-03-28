<?php

namespace Tests\Unit\Http\Requests\Discord;

use App\Http\Requests\Discord\SearchGuildMembersRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchGuildMembersRequestTest extends TestCase
{
    private function makeRequest(array $params = []): SearchGuildMembersRequest
    {
        return SearchGuildMembersRequest::create('/', 'GET', $params);
    }

    // ==================== rules ====================

    #[Test]
    public function rules_query_is_required_string(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('query', $rules);
        $this->assertContains('required', $rules['query']);
        $this->assertContains('string', $rules['query']);
    }

    #[Test]
    public function rules_limit_is_optional_integer_between_1_and_1000(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('limit', $rules);
        $this->assertContains('sometimes', $rules['limit']);
        $this->assertContains('integer', $rules['limit']);
        $this->assertContains('min:1', $rules['limit']);
        $this->assertContains('max:1000', $rules['limit']);
        $this->assertNotContains('required', $rules['limit']);
    }
}
