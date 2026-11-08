<?php

namespace Tests\Unit\Notifications;

use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Discord\Data\Messages\EmbedData;
use App\Models\Character;
use App\Models\PlayableClass;
use App\Models\RacePlacement;
use App\Notifications\RacePlacementClaimed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('characters')]
#[Group('discord-integration')]
class RacePlacementClaimedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{int, int}>
     */
    public static function medalColors(): array
    {
        return [
            'gold' => [1, 0xE3B341],
            'silver' => [2, 0xC3CBD0],
            'bronze' => [3, 0xD08A4F],
        ];
    }

    #[Test]
    #[DataProvider('medalColors')]
    public function it_colours_the_embed_by_medal(int $position, int $color): void
    {
        $embed = $this->embedFor($this->placement($position));

        $this->assertSame($color, $embed->color);
    }

    #[Test]
    public function it_links_to_the_race_page(): void
    {
        $embed = $this->embedFor($this->placement());

        $this->assertSame(route('race.index'), $embed->url);
    }

    #[Test]
    public function it_uses_the_character_render_as_the_image(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $placement = $this->placement();
        $placement->character
            ->addMedia(UploadedFile::fake()->image('render.png'))
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        $embed = $this->embedFor($placement);

        $this->assertNotNull($embed->image);
        $this->assertSame(
            $placement->character->getFirstMediaUrl(HasCharacterMedia::MEDIA_COLLECTION_RENDER),
            $embed->image->url,
        );
    }

    #[Test]
    public function it_omits_the_image_without_a_render(): void
    {
        $embed = $this->embedFor($this->placement());

        $this->assertInstanceOf(Optional::class, $embed->image);
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function medalTitles(): array
    {
        return [
            'gold' => [1, 'Gold', 'first'],
            'silver' => [2, 'Silver', 'second'],
            'bronze' => [3, 'Bronze', 'third'],
        ];
    }

    #[Test]
    #[DataProvider('medalTitles')]
    public function it_names_the_character_medal_and_ordinal_in_the_title(int $position, string $medal, string $ordinal): void
    {
        $placement = $this->placement($position);

        $embed = $this->embedFor($placement);

        $this->assertSame("{$medal}: {$placement->character->name} is {$ordinal} to level 60!", $embed->title);
    }

    #[Test]
    public function it_names_the_class_in_the_description(): void
    {
        $character = Character::factory()
            ->withPlayableClass(PlayableClass::factory()->create(['name' => 'Warlock']))
            ->create(['name' => 'Alpha']);

        $embed = $this->embedFor(RacePlacement::factory()->create(['character_id' => $character->id, 'position' => 1]));

        $this->assertSame('Congratulations to Alpha, the Warlock, for reaching level 60 in World of Warcraft: Forever.', $embed->description);
    }

    #[Test]
    public function it_omits_the_class_from_the_description_without_one(): void
    {
        $character = Character::factory()->create(['name' => 'Alpha']);

        $embed = $this->embedFor(RacePlacement::factory()->create(['character_id' => $character->id, 'position' => 1]));

        $this->assertSame('Congratulations to Alpha for reaching level 60 in World of Warcraft: Forever.', $embed->description);
    }

    #[Test]
    public function it_refuses_to_announce_a_placement_without_a_medal(): void
    {
        $placement = $this->placement(4);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Race placement {$placement->id} at position 4 has no medal to announce.");

        $this->embedFor($placement);
    }

    #[Test]
    public function it_stamps_the_embed_with_the_reach_time(): void
    {
        $placement = $this->placement();

        $embed = $this->embedFor($placement);

        $this->assertSame($placement->reached_at->toIso8601String(), $embed->timestamp);
    }

    private function placement(int $position = 1): RacePlacement
    {
        $character = Character::factory()->withPlayableClass(PlayableClass::factory()->create())->create();

        return RacePlacement::factory()->create([
            'character_id' => $character->id,
            'position' => $position,
        ]);
    }

    private function embedFor(RacePlacement $placement): EmbedData
    {
        return (new RacePlacementClaimed($placement))->toMessage()->embeds[0];
    }
}
