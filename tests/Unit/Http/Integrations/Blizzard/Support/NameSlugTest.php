<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Support;

use App\Http\Integrations\Blizzard\Support\NameSlug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('blizzard-integration')]
class NameSlugTest extends TestCase
{
    #[Test]
    #[DataProvider('names')]
    public function it_lowercases_names_keeping_diacritics_and_hyphenating_spaces(string $name, string $expected): void
    {
        $this->assertSame($expected, NameSlug::from($name));
    }

    #[Test]
    public function it_keeps_names_that_differ_only_by_an_accent_distinct(): void
    {
        $this->assertNotSame(NameSlug::from('Ozonà'), NameSlug::from('Ozòna'));
        $this->assertNotSame(NameSlug::from('Izepo'), NameSlug::from('Ízepo'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function names(): array
    {
        return [
            'plain name' => ['Foo', 'foo'],
            'accented capital' => ['Ízepo', 'ízepo'],
            'non-latin letter' => ['Kurorø', 'kurorø'],
            'spaces become hyphens' => ['Thräll Runetotem', 'thräll-runetotem'],
            'surrounding whitespace is trimmed' => ['  Lûrana ', 'lûrana'],
        ];
    }
}
