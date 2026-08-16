<?php

namespace Tests\Unit\Http\Integrations\Blizzard;

use App\Http\Integrations\Blizzard\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
class RegionTest extends TestCase
{
    #[Test]
    public function it_has_exactly_four_cases(): void
    {
        $this->assertCount(4, Region::cases());
    }

    #[Test]
    public function each_case_has_the_correct_value(): void
    {
        $this->assertSame('eu', Region::EU->value);
        $this->assertSame('us', Region::US->value);
        $this->assertSame('kr', Region::KR->value);
        $this->assertSame('tw', Region::TW->value);
    }

    // ==================== apiUrl ====================

    #[Test]
    public function api_url_returns_regional_blizzard_api_host(): void
    {
        $this->assertSame('https://eu.api.blizzard.com', Region::EU->apiUrl());
        $this->assertSame('https://us.api.blizzard.com', Region::US->apiUrl());
        $this->assertSame('https://kr.api.blizzard.com', Region::KR->apiUrl());
        $this->assertSame('https://tw.api.blizzard.com', Region::TW->apiUrl());
    }

    // ==================== tokenUrl ====================

    #[Test]
    public function token_url_returns_regional_battle_net_oauth_endpoint(): void
    {
        $this->assertSame('https://eu.battle.net/oauth/token', Region::EU->tokenUrl());
        $this->assertSame('https://us.battle.net/oauth/token', Region::US->tokenUrl());
        $this->assertSame('https://kr.battle.net/oauth/token', Region::KR->tokenUrl());
        $this->assertSame('https://tw.battle.net/oauth/token', Region::TW->tokenUrl());
    }

    // ==================== renderCdnUrl ====================

    #[Test]
    public function render_cdn_url_returns_regional_render_host(): void
    {
        $this->assertSame('https://render.worldofwarcraft.com/eu', Region::EU->renderCdnUrl());
        $this->assertSame('https://render.worldofwarcraft.com/us', Region::US->renderCdnUrl());
        $this->assertSame('https://render.worldofwarcraft.com/kr', Region::KR->renderCdnUrl());
        $this->assertSame('https://render.worldofwarcraft.com/tw', Region::TW->renderCdnUrl());
    }

    // ==================== locales ====================

    #[Test]
    public function locales_returns_the_supported_set_per_region(): void
    {
        $this->assertSame(
            ['en_GB', 'de_DE', 'es_ES', 'fr_FR', 'it_IT', 'pl_PL', 'pt_PT', 'ru_RU'],
            Region::EU->locales(),
        );
        $this->assertSame(['en_US', 'pt_BR', 'es_MX'], Region::US->locales());
        $this->assertSame(['ko_KR'], Region::KR->locales());
        $this->assertSame(['zh_TW'], Region::TW->locales());
    }

    // ==================== defaultLocale ====================

    #[Test]
    public function default_locale_returns_the_first_supported_locale(): void
    {
        $this->assertSame('en_GB', Region::EU->defaultLocale());
        $this->assertSame('en_US', Region::US->defaultLocale());
        $this->assertSame('ko_KR', Region::KR->defaultLocale());
        $this->assertSame('zh_TW', Region::TW->defaultLocale());
    }

    // ==================== supportsLocale ====================

    #[Test]
    public function supports_locale_returns_true_for_supported_locales(): void
    {
        $this->assertTrue(Region::EU->supportsLocale('en_GB'));
        $this->assertTrue(Region::EU->supportsLocale('ru_RU'));
        $this->assertTrue(Region::US->supportsLocale('pt_BR'));
        $this->assertTrue(Region::KR->supportsLocale('ko_KR'));
        $this->assertTrue(Region::TW->supportsLocale('zh_TW'));
    }

    #[Test]
    public function supports_locale_returns_false_for_locales_belonging_to_other_regions(): void
    {
        $this->assertFalse(Region::EU->supportsLocale('en_US'));
        $this->assertFalse(Region::EU->supportsLocale('ko_KR'));
        $this->assertFalse(Region::US->supportsLocale('en_GB'));
        $this->assertFalse(Region::KR->supportsLocale('zh_TW'));
        $this->assertFalse(Region::TW->supportsLocale('ko_KR'));
    }

    #[Test]
    public function supports_locale_returns_false_for_unknown_locales(): void
    {
        $this->assertFalse(Region::EU->supportsLocale('xx_XX'));
        $this->assertFalse(Region::EU->supportsLocale(''));
        $this->assertFalse(Region::US->supportsLocale('foo'));
    }

    #[Test]
    public function supports_locale_is_case_sensitive(): void
    {
        $this->assertFalse(Region::EU->supportsLocale('EN_GB'));
        $this->assertFalse(Region::EU->supportsLocale('en_gb'));
    }
}
