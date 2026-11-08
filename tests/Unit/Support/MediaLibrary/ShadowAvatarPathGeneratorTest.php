<?php

namespace Tests\Unit\Support\MediaLibrary;

use App\Contracts\HasShadowAvatars;
use App\Models\PlayableRace;
use App\Support\MediaLibrary\ShadowAvatarPathGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('media')]
#[Group('blizzard-integration')]
class ShadowAvatarPathGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    #[Test]
    public function it_stores_shadow_avatars_in_the_shared_mirror_directory(): void
    {
        $media = PlayableRace::factory()->create()
            ->addMediaFromString('BINARY')
            ->usingFileName('6-0.jpg')
            ->toMediaCollection(HasShadowAvatars::MEDIA_COLLECTION);
        $generator = new ShadowAvatarPathGenerator;

        $this->assertSame('blizzard-cdn/shadow/avatar/', $generator->getPath($media));
        $this->assertSame('blizzard-cdn/shadow/avatar/conversions/', $generator->getPathForConversions($media));
        $this->assertSame('blizzard-cdn/shadow/avatar/responsive-images/', $generator->getPathForResponsiveImages($media));
        $this->assertSame('blizzard-cdn/shadow/avatar/6-0.jpg', $media->getPathRelativeToRoot());
        Storage::disk('s3')->assertExists('blizzard-cdn/shadow/avatar/6-0.jpg');
    }

    #[Test]
    public function it_delegates_to_the_default_path_generator_for_other_collections(): void
    {
        $media = PlayableRace::factory()->create()
            ->addMediaFromString('BINARY')
            ->usingFileName('other.jpg')
            ->toMediaCollection('other');
        $generator = new ShadowAvatarPathGenerator;

        $this->assertStringNotContainsString('blizzard-cdn', $generator->getPath($media));
        $this->assertStringNotContainsString('blizzard-cdn', $generator->getPathForConversions($media));
        $this->assertStringNotContainsString('blizzard-cdn', $generator->getPathForResponsiveImages($media));
    }
}
