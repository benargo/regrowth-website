<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\UpdateRecruitmentMacroRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('recruitment')]
class UpdateRecruitmentMacroRequestTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function rules_accept_a_complete_macro(): void
    {
        $validator = $this->validate(['title' => 'Trade chat', 'type' => 'in_game', 'content' => 'Regrowth is recruiting!']);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    #[Group('validation')]
    public function rules_require_a_title_type_and_content(): void
    {
        $errors = $this->validate([])->errors();

        $this->assertSame('The title field is required.', $errors->first('title'));
        $this->assertSame('The type field is required.', $errors->first('type'));
        $this->assertSame('The content field is required.', $errors->first('content'));
    }

    #[Test]
    #[Group('validation')]
    public function rules_limit_the_title_to_100_characters(): void
    {
        $validator = $this->validate(['title' => str_repeat('x', 101), 'type' => 'in_game', 'content' => 'Hi']);

        $this->assertSame('The title field must not be greater than 100 characters.', $validator->errors()->first('title'));
    }

    #[Test]
    #[Group('validation')]
    public function rules_reject_an_unknown_type(): void
    {
        $validator = $this->validate(['title' => 'Trade chat', 'type' => 'carrier_pigeon', 'content' => 'Hi']);

        $this->assertSame('The selected type is invalid.', $validator->errors()->first('type'));
        $this->assertFalse($validator->errors()->has('content'));
    }

    #[Test]
    #[Group('validation')]
    public function rules_apply_the_in_game_limit_to_in_game_macros(): void
    {
        $validator = $this->validate(['title' => 'Trade chat', 'type' => 'in_game', 'content' => str_repeat('x', 256)]);

        $this->assertSame('The macro is 256 characters long; In-game chat allows 255.', $validator->errors()->first('content'));
    }

    #[Test]
    #[Group('validation')]
    public function rules_apply_the_discord_limit_to_discord_macros(): void
    {
        $this->assertTrue($this->validate(['title' => 'Post', 'type' => 'discord', 'content' => str_repeat('x', 2000)])->passes());
        $this->assertFalse($this->validate(['title' => 'Post', 'type' => 'discord', 'content' => str_repeat('x', 2001)])->passes());
    }

    // ==================== helpers ====================

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = UpdateRecruitmentMacroRequest::create('/', 'PATCH', $data);

        return Validator::make($data, $request->rules());
    }
}
