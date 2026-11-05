<?php

namespace Tests\Feature\Actions\RecruitmentMacros;

use App\Actions\RecruitmentMacros\CreateRecruitmentMacro;
use App\Enums\RecruitmentMacroType;
use App\Models\RecruitmentMacro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('recruitment')]
class CreateRecruitmentMacroTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_the_macro_with_its_creator(): void
    {
        $creator = User::factory()->create();

        $macro = CreateRecruitmentMacro::run($creator, [
            'title' => 'Trade chat',
            'type' => RecruitmentMacroType::InGame->value,
            'content' => 'Regrowth is recruiting!',
        ]);

        $this->assertTrue($macro->createdBy->is($creator));
        $this->assertSame('Trade chat', $macro->title);
        $this->assertSame(RecruitmentMacroType::InGame, $macro->type);
        $this->assertSame('Regrowth is recruiting!', $macro->content);
    }

    #[Test]
    public function it_places_the_macro_at_the_end_of_the_order(): void
    {
        $existing = RecruitmentMacro::factory()->create();

        $macro = CreateRecruitmentMacro::run(User::factory()->create(), [
            'title' => 'Discord post',
            'type' => RecruitmentMacroType::Discord->value,
            'content' => 'Join us.',
        ]);

        $this->assertSame([$existing->id, $macro->id], RecruitmentMacro::ordered()->pluck('id')->all());
    }
}
