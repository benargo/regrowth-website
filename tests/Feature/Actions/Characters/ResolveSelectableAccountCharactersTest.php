<?php

namespace Tests\Feature\Actions\Characters;

use App\Actions\Characters\ResolveSelectableAccountCharacters;
use App\Models\Character;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class ResolveSelectableAccountCharactersTest extends TestCase
{
    #[Test]
    public function it_keeps_only_the_mains_when_the_account_has_one(): void
    {
        $alt = $this->character(1, isMain: false);
        $main = $this->character(2, isMain: true);

        $selectable = ResolveSelectableAccountCharacters::run(new Collection([$alt, $main]));

        $this->assertSame([2], $selectable->modelKeys());
    }

    #[Test]
    public function it_keeps_the_highest_level_characters_in_order_when_none_is_a_main(): void
    {
        $first = $this->character(1, isMain: false, level: 70);
        $lowbie = $this->character(2, isMain: false, level: 23);
        $second = $this->character(3, isMain: false, level: 70);

        $selectable = ResolveSelectableAccountCharacters::run(new Collection([$first, $lowbie, $second]));

        $this->assertSame([1, 3], $selectable->modelKeys());
    }

    #[Test]
    public function it_uses_the_accounts_own_highest_level_rather_than_a_fixed_cap(): void
    {
        $alt = $this->character(1, isMain: false, level: 61);
        $highest = $this->character(2, isMain: false, level: 68);

        $selectable = ResolveSelectableAccountCharacters::run(new Collection([$alt, $highest]));

        $this->assertSame([2], $selectable->modelKeys());
    }

    #[Test]
    public function it_prefers_a_main_over_a_higher_level_alt(): void
    {
        $alt = $this->character(1, isMain: false, level: 70);
        $main = $this->character(2, isMain: true, level: 65);

        $selectable = ResolveSelectableAccountCharacters::run(new Collection([$alt, $main]));

        $this->assertSame([2], $selectable->modelKeys());
    }

    #[Test]
    public function it_keeps_every_character_in_order_when_none_has_a_level(): void
    {
        $first = $this->character(1, isMain: false, level: null);
        $second = $this->character(2, isMain: false, level: null);

        $selectable = ResolveSelectableAccountCharacters::run(new Collection([$first, $second]));

        $this->assertSame([1, 2], $selectable->modelKeys());
    }

    #[Test]
    public function it_returns_nothing_for_no_characters(): void
    {
        $this->assertTrue(ResolveSelectableAccountCharacters::run(new Collection)->isEmpty());
    }

    // ==================== helpers ====================

    private function character(int $id, bool $isMain, ?int $level = 70): Character
    {
        return Character::factory()->make(['id' => $id, 'is_main' => $isMain, 'level' => $level]);
    }
}
