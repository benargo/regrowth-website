<?php

namespace Tests\Feature\Rules;

use App\Enums\RecruitmentMacroType;
use App\Rules\RecruitmentMacroContent;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('recruitment')]
#[Group('validation')]
class RecruitmentMacroContentTest extends TestCase
{
    #[Test]
    public function it_accepts_an_in_game_macro_of_exactly_255_characters(): void
    {
        $this->assertTrue($this->validate(RecruitmentMacroType::InGame, str_repeat('é', 255))->passes());
    }

    #[Test]
    public function it_rejects_an_in_game_macro_of_256_characters(): void
    {
        $validator = $this->validate(RecruitmentMacroType::InGame, str_repeat('x', 256));

        $this->assertTrue($validator->fails());
        $this->assertSame('The macro is 256 characters long; In-game chat allows 255.', $validator->errors()->first('content'));
    }

    #[Test]
    public function it_accepts_a_discord_macro_of_exactly_2000_units(): void
    {
        $this->assertTrue($this->validate(RecruitmentMacroType::Discord, str_repeat('x', 2000))->passes());
    }

    #[Test]
    public function it_rejects_a_discord_macro_of_2001_units(): void
    {
        $validator = $this->validate(RecruitmentMacroType::Discord, str_repeat('x', 2001));

        $this->assertTrue($validator->fails());
        $this->assertSame('The macro is 2,001 characters long; Discord allows 2,000.', $validator->errors()->first('content'));
    }

    #[Test]
    public function it_counts_an_emoji_as_two_units_for_discord(): void
    {
        $this->assertFalse($this->validate(RecruitmentMacroType::Discord, str_repeat('x', 1999).'🌳')->passes());
    }

    private function validate(RecruitmentMacroType $type, string $content): Validator
    {
        return validator(['content' => $content], ['content' => ['required', 'string', new RecruitmentMacroContent($type)]]);
    }
}
