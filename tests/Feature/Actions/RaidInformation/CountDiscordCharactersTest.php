<?php

namespace Tests\Feature\Actions\RaidInformation;

use App\Actions\RaidInformation\CountDiscordCharacters;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class CountDiscordCharactersTest extends TestCase
{
    #[Test]
    public function it_counts_ascii_as_one_unit_each(): void
    {
        $this->assertSame(3, CountDiscordCharacters::run('abc'));
    }

    #[Test]
    public function it_counts_an_accented_letter_as_one(): void
    {
        $this->assertSame(1, CountDiscordCharacters::run('é'));
    }

    #[Test]
    public function it_counts_an_astral_emoji_as_two(): void
    {
        $this->assertSame(2, CountDiscordCharacters::run('🌳'));
    }

    #[Test]
    public function it_counts_a_discord_tag_at_its_raw_length(): void
    {
        $this->assertSame(30, CountDiscordCharacters::run('<:regrowth:123456789012345678>'));
    }
}
