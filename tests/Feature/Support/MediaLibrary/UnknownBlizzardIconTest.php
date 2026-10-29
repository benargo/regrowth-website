<?php

namespace Tests\Feature\Support\MediaLibrary;

use App\Support\MediaLibrary\UnknownBlizzardIcon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('media')]
class UnknownBlizzardIconTest extends TestCase
{
    #[Test]
    public function it_resolves_the_questionmark_path_and_url_on_the_current_media_disk(): void
    {
        Storage::fake('s3');
        $icon = app(UnknownBlizzardIcon::class);

        $this->assertSame('blizzard-cdn/icons/56/inv_misc_questionmark.jpg', $icon->path());
        $this->assertSame(Storage::disk('s3')->url('blizzard-cdn/icons/56/inv_misc_questionmark.jpg'), $icon->url());
    }

    #[Test]
    public function it_follows_a_disk_faked_after_it_was_resolved(): void
    {
        Storage::fake('s3');
        $icon = app(UnknownBlizzardIcon::class);

        Storage::fake('s3', ['url' => 'https://later.test']);

        $this->assertStringStartsWith('https://later.test/', $icon->url());
    }
}
