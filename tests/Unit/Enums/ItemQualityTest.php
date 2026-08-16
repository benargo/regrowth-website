<?php

namespace Tests\Unit\Enums;

use App\Enums\ItemQuality;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('loot')]
class ItemQualityTest extends TestCase
{
    #[Test]
    public function it_has_exactly_eight_cases(): void
    {
        $this->assertCount(8, ItemQuality::cases());
    }

    // ==================== values ====================

    #[Test]
    public function each_case_has_the_correct_quality_name_value(): void
    {
        $this->assertSame('Poor', ItemQuality::POOR->value);
        $this->assertSame('Common', ItemQuality::COMMON->value);
        $this->assertSame('Uncommon', ItemQuality::UNCOMMON->value);
        $this->assertSame('Rare', ItemQuality::RARE->value);
        $this->assertSame('Epic', ItemQuality::EPIC->value);
        $this->assertSame('Legendary', ItemQuality::LEGENDARY->value);
        $this->assertSame('Artifact', ItemQuality::ARTIFACT->value);
        $this->assertSame('Heirloom', ItemQuality::HEIRLOOM->value);
    }

    // ==================== colorCode ====================

    #[Test]
    public function each_case_returns_the_correct_color_code(): void
    {
        $this->assertSame(0x9D9D9D, ItemQuality::POOR->colorCode());
        $this->assertSame(0xFFFFFF, ItemQuality::COMMON->colorCode());
        $this->assertSame(0x1EFF00, ItemQuality::UNCOMMON->colorCode());
        $this->assertSame(0x0070DD, ItemQuality::RARE->colorCode());
        $this->assertSame(0xA335EE, ItemQuality::EPIC->colorCode());
        $this->assertSame(0xFF8000, ItemQuality::LEGENDARY->colorCode());
        $this->assertSame(0xE6CC80, ItemQuality::ARTIFACT->colorCode());
        $this->assertSame(0x00CCFF, ItemQuality::HEIRLOOM->colorCode());
    }

    // ==================== cssClass ====================

    #[Test]
    public function css_class_returns_lowercase_name_with_no_prefix_by_default(): void
    {
        $this->assertSame('-quality-poor', ItemQuality::POOR->cssClass());
        $this->assertSame('-quality-common', ItemQuality::COMMON->cssClass());
        $this->assertSame('-quality-uncommon', ItemQuality::UNCOMMON->cssClass());
        $this->assertSame('-quality-rare', ItemQuality::RARE->cssClass());
        $this->assertSame('-quality-epic', ItemQuality::EPIC->cssClass());
        $this->assertSame('-quality-legendary', ItemQuality::LEGENDARY->cssClass());
        $this->assertSame('-quality-artifact', ItemQuality::ARTIFACT->cssClass());
        $this->assertSame('-quality-heirloom', ItemQuality::HEIRLOOM->cssClass());
    }

    #[Test]
    public function css_class_prepends_the_given_prefix(): void
    {
        $this->assertSame('item-quality-poor', ItemQuality::POOR->cssClass('item'));
        $this->assertSame('item-quality-legendary', ItemQuality::LEGENDARY->cssClass('item'));
    }

    #[Test]
    public function css_class_trims_trailing_hyphens_from_the_prefix(): void
    {
        $this->assertSame('item-quality-poor', ItemQuality::POOR->cssClass('item-'));
        $this->assertSame('item-quality-poor', ItemQuality::POOR->cssClass('item---'));
    }
}
