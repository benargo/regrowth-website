<?php

namespace Tests\Feature\Actions\RecruitmentMacros;

use App\Actions\RecruitmentMacros\ReorderRecruitmentMacros;
use App\Models\RecruitmentMacro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('recruitment')]
class ReorderRecruitmentMacrosTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_the_new_order(): void
    {
        [$first, $second, $third] = RecruitmentMacro::factory()->count(3)->create();

        ReorderRecruitmentMacros::run([$third->id, $first->id, $second->id]);

        $this->assertSame([$third->id, $first->id, $second->id], RecruitmentMacro::ordered()->pluck('id')->all());
    }

    #[Test]
    public function it_rejects_a_list_missing_a_live_macro(): void
    {
        [$first, $second] = RecruitmentMacro::factory()->count(2)->create();

        $this->expectException(ValidationException::class);

        ReorderRecruitmentMacros::run([$second->id]);
    }

    #[Test]
    public function it_rejects_a_list_with_an_unknown_macro(): void
    {
        $macro = RecruitmentMacro::factory()->create();

        $this->expectException(ValidationException::class);

        ReorderRecruitmentMacros::run([$macro->id, (string) Str::uuid()]);
    }

    #[Test]
    public function it_rejects_a_list_with_a_trashed_macro(): void
    {
        $live = RecruitmentMacro::factory()->create();
        $trashed = RecruitmentMacro::factory()->create();
        $trashed->delete();

        try {
            ReorderRecruitmentMacros::run([$trashed->id, $live->id]);
            $this->fail('A trashed macro was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['The macro list has changed; reload the page and try again.'], $exception->errors()['macro_ids']);
        }
    }
}
