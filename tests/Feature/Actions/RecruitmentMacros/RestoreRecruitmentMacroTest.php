<?php

namespace Tests\Feature\Actions\RecruitmentMacros;

use App\Actions\RecruitmentMacros\RestoreRecruitmentMacro;
use App\Models\RecruitmentMacro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('recruitment')]
class RestoreRecruitmentMacroTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_restores_the_macro_to_the_end_of_the_order(): void
    {
        [$first, $second, $third] = RecruitmentMacro::factory()->count(3)->create();
        $first->delete();

        RestoreRecruitmentMacro::run($first);

        $this->assertNotSoftDeleted($first);
        $this->assertSame([$second->id, $third->id, $first->id], RecruitmentMacro::ordered()->pluck('id')->all());
    }
}
