<?php

namespace Tests\Unit\Enums;

use App\Enums\RecruitmentMacroType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('recruitment')]
class RecruitmentMacroTypeTest extends TestCase
{
    #[Test]
    public function it_has_exactly_two_cases(): void
    {
        $this->assertCount(2, RecruitmentMacroType::cases());
    }

    /**
     * @return array<string, array{RecruitmentMacroType, string, string, int}>
     */
    public static function typeProvider(): array
    {
        return [
            'in game' => [RecruitmentMacroType::InGame, 'in_game', 'In-game chat', 255],
            'discord' => [RecruitmentMacroType::Discord, 'discord', 'Discord', 2000],
        ];
    }

    #[Test]
    #[DataProvider('typeProvider')]
    public function each_case_has_its_value_label_and_character_limit(RecruitmentMacroType $type, string $value, string $label, int $limit): void
    {
        $this->assertSame($value, $type->value);
        $this->assertSame($label, $type->label());
        $this->assertSame($limit, $type->characterLimit());
    }

    #[Test]
    public function in_game_macros_count_characters(): void
    {
        $this->assertSame(4, RecruitmentMacroType::InGame->countCharacters('Héé🌳'));
    }

    #[Test]
    public function discord_macros_count_utf16_units(): void
    {
        $this->assertSame(5, RecruitmentMacroType::Discord->countCharacters('Héé🌳'));
    }
}
