<?php

namespace Tests\Unit\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Data\Messages\EmbedProviderData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\Support\Data\AssertsDataValidation;
use Tests\TestCase;

#[Group('discord-integration')]
class EmbedProviderDataTest extends TestCase
{
    use AssertsDataValidation;

    #[Test]
    public function it_leaves_every_omitted_field_optional(): void
    {
        $provider = EmbedProviderData::from([]);

        $this->assertInstanceOf(Optional::class, $provider->name);
        $this->assertInstanceOf(Optional::class, $provider->url);
    }

    #[Test]
    public function it_hydrates_every_field(): void
    {
        $provider = EmbedProviderData::from([
            'name' => 'YouTube',
            'url' => 'https://youtube.com',
        ]);

        $this->assertSame('YouTube', $provider->name);
        $this->assertSame('https://youtube.com', $provider->url);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_null_name(): void
    {
        $this->assertDataRejects(EmbedProviderData::class, ['name' => null], 'name');
    }
}
