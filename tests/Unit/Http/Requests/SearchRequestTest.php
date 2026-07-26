<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\SearchRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class SearchRequestTest extends TestCase
{
    #[Test]
    public function it_requires_q_between_two_and_one_hundred_characters(): void
    {
        $rules = (new SearchRequest)->rules();

        $this->assertSame(['required', 'string', 'min:2', 'max:100'], $rules['q']);
    }

    #[Test]
    public function it_allows_raid_id_to_be_omitted(): void
    {
        $rules = (new SearchRequest)->rules();

        $this->assertSame(['sometimes', 'nullable', 'integer', 'exists:raids,id'], $rules['raid_id']);
    }

    #[Test]
    public function raid_id_returns_null_when_absent(): void
    {
        $request = SearchRequest::create('/api/search', 'GET', ['q' => 'archbishop']);

        $this->assertNull($request->raidId());
    }

    #[Test]
    public function raid_id_returns_an_integer_when_present(): void
    {
        $request = SearchRequest::create('/api/search', 'GET', ['q' => 'archbishop', 'raid_id' => '5']);

        $this->assertSame(5, $request->raidId());
    }

    #[Test]
    public function it_squishes_and_lowercases_q(): void
    {
        $request = SearchRequest::create('/api/search', 'GET', ['q' => '  ArcH   Bishop  ']);

        $request->prepareForValidationForTesting();

        $this->assertSame('arch bishop', $request->input('q'));
    }

    #[Test]
    public function it_strips_full_text_boolean_mode_operators_from_q(): void
    {
        $request = SearchRequest::create('/api/search', 'GET', ['q' => 'arch+bishop*']);

        $request->prepareForValidationForTesting();

        $this->assertSame('arch bishop', $request->input('q'));
    }

    #[Test]
    public function it_reduces_a_term_of_only_operators_to_an_empty_string(): void
    {
        $request = SearchRequest::create('/api/search', 'GET', ['q' => '***']);

        $request->prepareForValidationForTesting();

        // The min:2 rule then rejects it, so no defensive empty-term branch is
        // needed in Item::matchingName().
        $this->assertSame('', $request->input('q'));
    }
}
