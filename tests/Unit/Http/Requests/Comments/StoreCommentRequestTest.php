<?php

namespace Tests\Unit\Http\Requests\Comments;

use App\Http\Requests\Comments\StoreCommentRequest;
use App\Models\Item;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('comments')]
class StoreCommentRequestTest extends TestCase
{
    private function makeRequest(array $data = []): StoreCommentRequest
    {
        return StoreCommentRequest::create('/', 'POST', $data);
    }

    #[Test]
    public function rules_require_a_body_within_length_bounds(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('body', $rules);
        $this->assertContains('required', $rules['body']);
        $this->assertContains('string', $rules['body']);
        $this->assertContains('min:3', $rules['body']);
        $this->assertContains('max:5000', $rules['body']);
    }

    #[Test]
    public function rules_restrict_commentable_type_to_an_allow_list(): void
    {
        $rules = $this->makeRequest()->rules();

        $this->assertArrayHasKey('commentable_type', $rules);
        $this->assertContains('required', $rules['commentable_type']);

        $in = collect($rules['commentable_type'])->first(fn ($rule) => $rule instanceof In);

        $this->assertNotNull($in, 'commentable_type must be constrained by Rule::in().');
        $this->assertSame((string) Rule::in([Item::class]), (string) $in);
    }

    #[Test]
    public function rules_require_commentable_id_to_exist(): void
    {
        $rules = $this->makeRequest(['commentable_type' => Item::class])->rules();

        $this->assertArrayHasKey('commentable_id', $rules);
        $this->assertContains('required', $rules['commentable_id']);
        $this->assertTrue(
            collect($rules['commentable_id'])->contains(fn ($rule) => $rule instanceof Exists),
            'commentable_id must carry an exists rule.'
        );
    }

    #[Test]
    public function commentable_id_exists_rule_targets_the_resolved_table(): void
    {
        $rules = $this->makeRequest(['commentable_type' => Item::class])->rules();

        $exists = collect($rules['commentable_id'])->first(fn ($rule) => $rule instanceof Exists);

        $this->assertStringContainsString('items', (string) $exists);
    }

    #[Test]
    public function commentable_id_falls_back_to_a_non_matching_rule_for_an_unknown_type(): void
    {
        $rules = $this->makeRequest(['commentable_type' => 'App\\Models\\NotAThing'])->rules();

        $this->assertArrayHasKey('commentable_id', $rules);
        $this->assertContains('required', $rules['commentable_id']);
    }
}
