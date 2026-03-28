<?php

namespace Tests\Unit\Enums;

use App\Enums\ItemQuality;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ItemQualityTest extends TestCase
{
    // ==================== cases ====================

    #[Test]
    public function it_has_exactly_eight_cases(): void
    {
        $this->assertCount(8, ItemQuality::cases());
    }

    #[Test]
    public function each_case_has_the_correct_hex_color(): void
    {
        $this->assertSame('9d9d9d', ItemQuality::Poor->value);
        $this->assertSame('ffffff', ItemQuality::Common->value);
        $this->assertSame('1eff00', ItemQuality::Uncommon->value);
        $this->assertSame('0070dd', ItemQuality::Rare->value);
        $this->assertSame('a335ee', ItemQuality::Epic->value);
        $this->assertSame('ff8000', ItemQuality::Legendary->value);
        $this->assertSame('e6cc80', ItemQuality::Artifact->value);
        $this->assertSame('00ccff', ItemQuality::Heirloom->value);
    }

    // ==================== cssClass ====================

    #[Test]
    public function css_class_returns_lowercase_name_prefixed(): void
    {
        $this->assertSame('item-quality-poor', ItemQuality::Poor->cssClass());
        $this->assertSame('item-quality-common', ItemQuality::Common->cssClass());
        $this->assertSame('item-quality-uncommon', ItemQuality::Uncommon->cssClass());
        $this->assertSame('item-quality-rare', ItemQuality::Rare->cssClass());
        $this->assertSame('item-quality-epic', ItemQuality::Epic->cssClass());
        $this->assertSame('item-quality-legendary', ItemQuality::Legendary->cssClass());
        $this->assertSame('item-quality-artifact', ItemQuality::Artifact->cssClass());
        $this->assertSame('item-quality-heirloom', ItemQuality::Heirloom->cssClass());
    }

    // ==================== fromName ====================

    #[Test]
    public function from_name_returns_correct_case_for_exact_match(): void
    {
        $this->assertSame(ItemQuality::Rare, ItemQuality::fromName('Rare'));
    }

    #[Test]
    public function from_name_is_case_insensitive(): void
    {
        $this->assertSame(ItemQuality::Epic, ItemQuality::fromName('epic'));
        $this->assertSame(ItemQuality::Epic, ItemQuality::fromName('EPIC'));
        $this->assertSame(ItemQuality::Epic, ItemQuality::fromName('ePiC'));
    }

    #[Test]
    public function from_name_returns_null_for_unknown_name(): void
    {
        $this->assertNull(ItemQuality::fromName('Mythic'));
        $this->assertNull(ItemQuality::fromName(''));
    }
}
