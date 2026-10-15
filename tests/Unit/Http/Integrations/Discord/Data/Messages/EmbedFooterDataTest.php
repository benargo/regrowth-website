<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Data\Messages\EmbedFooterData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\Support\Data\AssertsDataValidation;
use Tests\TestCase;

#[Group('discord-integration')]
class EmbedFooterDataTest extends TestCase
{
    use AssertsDataValidation;

    #[Test]
    public function it_hydrates_the_required_text_and_leaves_icons_optional(): void
    {
        $footer = EmbedFooterData::from(['text' => 'My Footer']);

        $this->assertSame('My Footer', $footer->text);
        $this->assertInstanceOf(Optional::class, $footer->iconUrl);
        $this->assertInstanceOf(Optional::class, $footer->proxyIconUrl);
    }

    #[Test]
    public function it_hydrates_the_icon_urls_from_snake_case_keys(): void
    {
        $footer = EmbedFooterData::from([
            'text' => 'Footer',
            'icon_url' => 'https://example.com/icon.png',
            'proxy_icon_url' => 'https://proxy.example.com/icon.png',
        ]);

        $this->assertSame('https://example.com/icon.png', $footer->iconUrl);
        $this->assertSame('https://proxy.example.com/icon.png', $footer->proxyIconUrl);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_null_icon_url(): void
    {
        $this->assertDataRejects(EmbedFooterData::class, ['text' => 'Footer', 'icon_url' => null], 'icon_url');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_text_over_two_thousand_and_forty_eight_characters(): void
    {
        $this->assertDataRejects(EmbedFooterData::class, ['text' => str_repeat('a', 2049)], 'text');
    }
}
