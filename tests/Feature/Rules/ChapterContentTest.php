<?php

namespace Tests\Feature\Rules;

use App\Rules\ChapterContent;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ChapterContentTest extends TestCase
{
    #[Test]
    public function it_accepts_exactly_two_thousand_units(): void
    {
        $this->assertTrue($this->passes($this->padded("## A\n", 2000)));
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_two_thousand_and_one_units(): void
    {
        $validator = $this->validate($this->padded("## A\n", 2001));

        $this->assertTrue($validator->fails());
        $this->assertSame('The chapter is 2,001 characters long; Discord allows 2,000.', $validator->errors()->first('content'));
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_an_emoji_that_crosses_the_limit(): void
    {
        $this->assertFalse($this->passes($this->padded("## A\n", 1999).'🌳'));
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_a_level_two_heading_for_a_chapter(): void
    {
        foreach (['# A', '### A', 'A'] as $content) {
            $this->assertFalse($this->passes($content), $content);
        }

        $this->assertTrue($this->passes('## A'));
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_a_level_one_heading_for_the_introduction(): void
    {
        $this->assertFalse($this->passes('## A', new ChapterContent(isIntroduction: true)));
        $this->assertTrue($this->passes('# A', new ChapterContent(isIntroduction: true)));
    }

    #[Test]
    #[Group('validation')]
    public function it_explains_the_required_chapter_heading(): void
    {
        $validator = $this->validate('### A');

        $this->assertTrue($validator->fails());
        $this->assertSame('The first line must be a heading starting with "## ".', $validator->errors()->first('content'));
    }

    #[Test]
    #[Group('validation')]
    public function it_explains_the_required_introduction_heading(): void
    {
        $validator = $this->validate('## A', new ChapterContent(isIntroduction: true));

        $this->assertTrue($validator->fails());
        $this->assertSame('The first line must be a heading starting with "# ".', $validator->errors()->first('content'));
    }

    #[Test]
    #[Group('validation')]
    public function it_reads_the_heading_from_the_first_line_of_any_line_ending(): void
    {
        $this->assertTrue($this->passes("## A\r\nB"));
        $this->assertTrue($this->passes("## A\rB"));
        $this->assertFalse($this->passes("A\r\n## B"));
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_a_level_one_heading_for_the_introduction_slug(): void
    {
        $this->assertTrue($this->passes('# A', ChapterContent::forSlug('introduction')));
        $this->assertFalse($this->passes('## A', ChapterContent::forSlug('introduction')));
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_a_level_two_heading_for_any_other_slug(): void
    {
        $this->assertTrue($this->passes('## A', ChapterContent::forSlug('some-chapter')));
        $this->assertFalse($this->passes('# A', ChapterContent::forSlug('some-chapter')));
    }

    // ==================== helpers ====================

    private function padded(string $prefix, int $units): string
    {
        return $prefix.str_repeat('x', $units - strlen($prefix));
    }

    private function validate(string $content, ?ChapterContent $rule = null): Validator
    {
        return validator(['content' => $content], ['content' => ['required', 'string', $rule ?? new ChapterContent]]);
    }

    private function passes(string $content, ?ChapterContent $rule = null): bool
    {
        return $this->validate($content, $rule)->passes();
    }
}
