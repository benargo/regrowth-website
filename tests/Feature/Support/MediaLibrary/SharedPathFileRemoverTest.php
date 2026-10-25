<?php

namespace Tests\Feature\Support\MediaLibrary;

use App\Contracts\HasBlizzardIcons;
use App\Contracts\HasCharacterMedia;
use App\Models\Character;
use App\Models\Item;
use App\Models\Raid;
use App\Support\MediaLibrary\UnknownBlizzardIcon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

#[Group('media')]
class SharedPathFileRemoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_keeps_the_new_file_when_a_same_named_portrait_replaces_the_old_one(): void
    {
        $character = Character::factory()->create();
        $character->addMediaFromString('OLD')
            ->usingFileName('51042439-avatar.jpg')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION);

        $character->addMediaFromString('NEW')
            ->usingFileName('51042439-avatar.jpg')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION);

        $portraits = $character->fresh()->getMedia(HasCharacterMedia::MEDIA_COLLECTION);
        $this->assertCount(1, $portraits);
        Storage::disk('s3')->assertExists($portraits->first()->getPathRelativeToRoot());
        $this->assertSame('NEW', Storage::disk('s3')->get($portraits->first()->getPathRelativeToRoot()));
    }

    #[Test]
    public function it_keeps_an_icon_file_that_another_item_still_uses(): void
    {
        $deletedItem = $this->itemWithIcon('inv_sword_01.jpg');
        $keptItem = $this->itemWithIcon('inv_sword_01.jpg');
        $path = $keptItem->getFirstMedia(HasBlizzardIcons::MEDIA_COLLECTION)->getPathRelativeToRoot();

        $deletedItem->delete();

        Storage::disk('s3')->assertExists($path);
        $this->assertSame(1, Media::where('file_name', 'inv_sword_01.jpg')->count());
    }

    #[Test]
    public function it_removes_an_icon_file_when_its_only_owner_is_deleted(): void
    {
        $item = $this->itemWithIcon('inv_axe_02.jpg');
        $path = $item->getFirstMedia(HasBlizzardIcons::MEDIA_COLLECTION)->getPathRelativeToRoot();
        Storage::disk('s3')->put('blizzard-cdn/icons/56/inv_other.jpg', 'OTHER');

        $item->delete();

        Storage::disk('s3')->assertMissing($path);
        Storage::disk('s3')->assertExists('blizzard-cdn/icons/56/inv_other.jpg');
    }

    #[Test]
    public function it_keeps_the_questionmark_icon_when_its_only_owner_is_deleted(): void
    {
        $item = $this->itemWithIcon(HasBlizzardIcons::BLIZZARD_UNKNOWN_ICON.'.'.HasBlizzardIcons::DEFAULT_MEDIA_FILE_EXTENSION);
        $path = $item->getFirstMedia(HasBlizzardIcons::MEDIA_COLLECTION)->getPathRelativeToRoot();

        $item->delete();

        $this->assertSame(app(UnknownBlizzardIcon::class)->path(), $path);
        Storage::disk('s3')->assertExists($path);
    }

    #[Test]
    public function it_fully_removes_media_outside_the_shared_directories(): void
    {
        $raid = Raid::factory()->create();
        $media = $raid->addMedia(UploadedFile::fake()->image('banner.jpg', 200, 100))
            ->toMediaCollection(Raid::MEDIA_BANNER);
        $directory = dirname($media->getPathRelativeToRoot());

        $media->delete();

        $this->assertSame([], Storage::disk('s3')->allFiles($directory));
    }

    /**
     * Create an item whose icon media is stored under the given file name.
     */
    private function itemWithIcon(string $fileName): Item
    {
        $item = Item::factory()->create();
        $item->addMediaFromString('ICON')
            ->usingFileName($fileName)
            ->withCustomProperties(['size' => HasBlizzardIcons::DEFAULT_MEDIA_SIZE])
            ->toMediaCollection(HasBlizzardIcons::MEDIA_COLLECTION);

        return $item;
    }
}
