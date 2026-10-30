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

    // ==================== heading level ====================

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

    // ==================== heading mentions ====================

    #[Test]
    #[Group('validation')]
    public function it_rejects_mentions_and_custom_emoji_in_the_heading(): void
    {
        $headings = [
            '## A <#123>',
            '## A <@&123>',
            '## A <@123>',
            '## A <@!123>',
            '## A <:regrowth:123>',
            '## A<a:party:123>',
            '## 🌳 A <:regrowth:123>',
            '## <:regrowth:123> <@&456>',
            '## <:regrowth:123> A <:party:456>',
        ];

        foreach ($headings as $content) {
            $this->assertFalse($this->passes($content), $content);
        }

        $this->assertFalse($this->passes('# A <#123>', new ChapterContent(isIntroduction: true)));
    }

    #[Test]
    public function it_accepts_one_leading_custom_emoji_and_mentions_below_the_heading(): void
    {
        $this->assertTrue($this->passes('## <:regrowth:123> Loot'));
        $this->assertTrue($this->passes('## <a:party:123> Fun'));
        $this->assertTrue($this->passes("## 🌳 A\n<#123> <@&456> <:regrowth:789>"));
    }

    #[Test]
    #[Group('validation')]
    public function it_explains_that_the_heading_cannot_hold_mentions(): void
    {
        $validator = $this->validate('## A <@&123>');

        $this->assertTrue($validator->fails());
        $this->assertSame("The heading can't contain mentions or custom emoji, apart from one custom emoji at the start.", $validator->errors()->first('content'));
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
