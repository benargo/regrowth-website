<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreHandbookChapterRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('raiding')]
class StoreHandbookChapterRequestTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function rules_accept_content_opening_with_a_chapter_heading(): void
    {
        $validator = $this->validate(['content' => "## Loot\n\nLoot rules."]);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    #[Group('validation')]
    public function rules_require_content(): void
    {
        $validator = $this->validate([]);

        $this->assertSame('The content field is required.', $validator->errors()->first('content'));
    }

    #[Test]
    #[Group('validation')]
    #[TestWith([42])]
    #[TestWith([['## Loot']])]
    public function rules_require_content_to_be_a_string(mixed $content): void
    {
        $validator = $this->validate(['content' => $content]);

        $this->assertSame(['The content field must be a string.'], $validator->errors()->get('content'));
    }

    #[Test]
    #[Group('validation')]
    public function rules_reject_content_opening_with_an_introduction_heading(): void
    {
        $validator = $this->validate(['content' => "# Loot\n\nLoot rules."]);

        $this->assertSame('The first line must be a heading starting with "## ".', $validator->errors()->first('content'));
    }

    // ==================== helpers ====================

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = StoreHandbookChapterRequest::create('/', 'POST', $data);

        return Validator::make($data, $request->rules());
    }
}
