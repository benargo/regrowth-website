<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\UpdateHandbookChapterRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('raiding')]
class UpdateHandbookChapterRequestTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function rules_accept_chapter_content_with_a_base_hash(): void
    {
        $validator = $this->validate('loot', [
            'content' => "## Loot\n\nLoot rules.",
            'base_hash' => str_repeat('a', 40),
        ]);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    #[Group('validation')]
    public function rules_require_content_and_base_hash(): void
    {
        $validator = $this->validate('loot', []);

        $this->assertSame('The content field is required.', $validator->errors()->first('content'));
        $this->assertSame('The base hash field is required.', $validator->errors()->first('base_hash'));
    }

    #[Test]
    #[Group('validation')]
    #[TestWith([42])]
    #[TestWith([['## Loot']])]
    public function rules_require_content_to_be_a_string(mixed $content): void
    {
        $validator = $this->validate('loot', [
            'content' => $content,
            'base_hash' => str_repeat('a', 40),
        ]);

        $this->assertSame(['The content field must be a string.'], $validator->errors()->get('content'));
    }

    #[Test]
    #[Group('validation')]
    public function rules_require_base_hash_to_be_a_string(): void
    {
        $validator = $this->validate('loot', [
            'content' => '## Loot',
            'base_hash' => 1234567890,
        ]);

        $this->assertSame('The base hash field must be a string.', $validator->errors()->first('base_hash'));
    }

    #[Test]
    #[Group('validation')]
    public function rules_require_base_hash_to_be_forty_characters(): void
    {
        $validator = $this->validate('loot', [
            'content' => '## Loot',
            'base_hash' => str_repeat('a', 39),
        ]);

        $this->assertSame('The base hash field must be 40 characters.', $validator->errors()->first('base_hash'));
    }

    #[Test]
    #[Group('validation')]
    public function rules_require_a_chapter_heading_for_a_chapter(): void
    {
        $validator = $this->validate('loot', [
            'content' => '# Loot',
            'base_hash' => str_repeat('a', 40),
        ]);

        $this->assertSame('The first line must be a heading starting with "## ".', $validator->errors()->first('content'));
    }

    #[Test]
    #[Group('validation')]
    public function rules_require_an_introduction_heading_for_the_introduction(): void
    {
        $validator = $this->validate('introduction', [
            'content' => '## Introduction',
            'base_hash' => str_repeat('a', 40),
        ]);

        $this->assertSame('The first line must be a heading starting with "# ".', $validator->errors()->first('content'));
    }

    // ==================== helpers ====================

    private function validate(string $slug, array $data): \Illuminate\Validation\Validator
    {
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('parameter')->with('chapter', null)->andReturn($slug);

        $request = UpdateHandbookChapterRequest::create('/', 'PATCH', $data);
        $request->setRouteResolver(fn () => $route);

        return Validator::make($data, $request->rules());
    }
}
