<?php

namespace Tests\Unit\Enums;

use App\Enums\SiteSection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('platform')]
class SiteSectionTest extends TestCase
{
    #[Test]
    #[DataProvider('features')]
    public function it_declares_what_each_section_shows(SiteSection $section, string $predicate, bool $expected): void
    {
        $this->assertSame($expected, $section->{$predicate}());
    }

    /**
     * @return array<string, array{SiteSection, string, bool}>
     */
    public static function features(): array
    {
        return [
            'raiding shows strategy' => [SiteSection::Raiding, 'showsStrategy', true],
            'loot hides strategy' => [SiteSection::Loot, 'showsStrategy', false],
            'loot shows item comments' => [SiteSection::Loot, 'showsItemComments', true],
            'raiding hides item comments' => [SiteSection::Raiding, 'showsItemComments', false],
        ];
    }

    #[Test]
    #[DataProvider('featureFlags')]
    public function it_lists_its_feature_flags_for_the_frontend(SiteSection $section, array $expected): void
    {
        $this->assertSame($expected, $section->features());
    }

    /**
     * @return array<string, array{SiteSection, array{itemComments: bool, strategy: bool}}>
     */
    public static function featureFlags(): array
    {
        return [
            'raiding' => [SiteSection::Raiding, ['itemComments' => false, 'strategy' => true]],
            'loot' => [SiteSection::Loot, ['itemComments' => true, 'strategy' => false]],
        ];
    }
}
