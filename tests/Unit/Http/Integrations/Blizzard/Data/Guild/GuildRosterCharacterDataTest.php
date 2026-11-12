<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Guild;

use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterCharacterData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('blizzard-integration')]
class GuildRosterCharacterDataTest extends TestCase
{
    #[Test]
    public function it_casts_a_guild_roster_character(): void
    {
        $dto = GuildRosterCharacterData::from([
            'id' => 54321,
            'name' => 'Sylvanas',
            'level' => 60,
            'playable_class' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/4'],
                'name' => 'Rogue',
                'id' => 4,
            ],
            'playable_race' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/5'],
                'name' => 'Undead',
                'id' => 5,
            ],
            'realm' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/realm/1605'],
                'name' => 'Silvermoon',
                'id' => 1605,
            ],
        ]);

        $this->assertSame(54321, $dto->id);
        $this->assertSame('Sylvanas', $dto->name);
        $this->assertSame(60, $dto->level);
        $this->assertInstanceOf(LinkData::class, $dto->playableClass);
        $this->assertSame('Rogue', $dto->playableClass->name);
        $this->assertSame(4, $dto->playableClass->id);
        $this->assertInstanceOf(LinkData::class, $dto->playableRace);
        $this->assertSame('Undead', $dto->playableRace->name);
        $this->assertSame(5, $dto->playableRace->id);
        $this->assertInstanceOf(LinkData::class, $dto->realm);
        $this->assertSame('Silvermoon', $dto->realm->name);
        $this->assertSame(1605, $dto->realm->id);
    }

    #[Test]
    public function it_casts_a_forever_roster_character_with_a_surname_and_no_realm(): void
    {
        $dto = GuildRosterCharacterData::from($this->foreverPayload());

        $this->assertSame('Stormrage', $dto->surname);
        $this->assertInstanceOf(Optional::class, $dto->realm);
    }

    #[Test]
    public function full_name_joins_the_name_and_surname(): void
    {
        $dto = GuildRosterCharacterData::from($this->foreverPayload());

        $this->assertSame('Thrall Stormrage', $dto->fullName());
    }

    #[Test]
    public function full_name_is_the_name_alone_without_a_surname(): void
    {
        $payload = $this->foreverPayload();
        unset($payload['surname']);

        $dto = GuildRosterCharacterData::from($payload);

        $this->assertSame('Thrall', $dto->fullName());
    }

    #[Test]
    public function surname_for_version_is_the_surname_when_the_version_uses_surnames(): void
    {
        $dto = GuildRosterCharacterData::from($this->foreverPayload());

        $this->assertSame('Stormrage', $dto->surnameForVersion(true));
    }

    #[Test]
    public function surname_for_version_is_null_when_the_version_has_no_surnames(): void
    {
        $dto = GuildRosterCharacterData::from($this->foreverPayload());

        $this->assertNull($dto->surnameForVersion(false));
    }

    #[Test]
    public function surname_for_version_is_null_without_a_surname(): void
    {
        $payload = $this->foreverPayload();
        unset($payload['surname']);

        $dto = GuildRosterCharacterData::from($payload);

        $this->assertNull($dto->surnameForVersion(true));
    }

    /**
     * @return array<string, mixed>
     */
    private function foreverPayload(): array
    {
        return [
            'id' => 54321,
            'name' => 'Thrall',
            'surname' => 'Stormrage',
            'slug' => 'thrall-stormrage',
            'level' => 60,
            'playable_class' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/7'],
                'id' => 7,
            ],
            'playable_race' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/2'],
                'id' => 2,
            ],
            'faction' => ['type' => 'HORDE'],
        ];
    }
}
