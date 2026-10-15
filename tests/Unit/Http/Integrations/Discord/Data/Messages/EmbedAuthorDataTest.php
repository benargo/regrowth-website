<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Data\Messages\EmbedAuthorData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\Support\Data\AssertsDataValidation;
use Tests\TestCase;

#[Group('discord-integration')]
class EmbedAuthorDataTest extends TestCase
{
    use AssertsDataValidation;

    #[Test]
    public function it_hydrates_the_required_name_and_leaves_the_rest_optional(): void
    {
        $author = EmbedAuthorData::from(['name' => 'John Doe']);

        $this->assertSame('John Doe', $author->name);
        $this->assertInstanceOf(Optional::class, $author->url);
        $this->assertInstanceOf(Optional::class, $author->iconUrl);
        $this->assertInstanceOf(Optional::class, $author->proxyIconUrl);
    }

    #[Test]
    public function it_hydrates_every_optional_field(): void
    {
        $author = EmbedAuthorData::from([
            'name' => 'John Doe',
            'url' => 'https://example.com/author',
            'icon_url' => 'https://example.com/author.png',
            'proxy_icon_url' => 'https://proxy.example.com/author.png',
        ]);

        $this->assertSame('https://example.com/author', $author->url);
        $this->assertSame('https://example.com/author.png', $author->iconUrl);
        $this->assertSame('https://proxy.example.com/author.png', $author->proxyIconUrl);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_null_url(): void
    {
        $this->assertDataRejects(EmbedAuthorData::class, ['name' => 'John Doe', 'url' => null], 'url');
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_name_over_two_hundred_and_fifty_six_characters(): void
    {
        $this->assertDataRejects(EmbedAuthorData::class, ['name' => str_repeat('a', 257)], 'name');
    }
}
