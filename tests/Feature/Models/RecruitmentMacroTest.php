<?php

namespace Tests\Feature\Models;

use App\Enums\RecruitmentMacroType;
use App\Models\RecruitmentMacro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('recruitment')]
class RecruitmentMacroTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_the_user_who_created_it(): void
    {
        $user = User::factory()->create();

        $macro = RecruitmentMacro::factory()->for($user, 'createdBy')->create();

        $this->assertTrue($macro->createdBy->is($user));
    }

    #[Test]
    public function it_casts_the_type_to_the_enum(): void
    {
        $macro = RecruitmentMacro::factory()->discord()->create();

        $this->assertSame(RecruitmentMacroType::Discord, $macro->fresh()->type);
    }

    #[Test]
    public function it_appends_new_macros_to_the_end_of_the_order(): void
    {
        $first = RecruitmentMacro::factory()->create();
        $second = RecruitmentMacro::factory()->create();

        $this->assertSame([$first->id, $second->id], RecruitmentMacro::ordered()->pluck('id')->all());
    }

    #[Test]
    public function it_prunes_only_macros_trashed_at_least_thirty_days_ago(): void
    {
        $live = RecruitmentMacro::factory()->create();
        $recent = RecruitmentMacro::factory()->create();
        $recent->delete();
        $old = RecruitmentMacro::factory()->create();
        $this->travel(-30)->days(fn () => $old->delete());

        $this->assertSame([$old->id], (new RecruitmentMacro)->prunable()->pluck('id')->all());

        $this->artisan('model:prune', ['--model' => [RecruitmentMacro::class]])->assertSuccessful();

        $this->assertDatabaseMissing('recruitment_macros', ['id' => $old->id]);
        $this->assertSoftDeleted($recent);
        $this->assertNotSoftDeleted($live);
    }

    #[Test]
    public function it_keeps_the_macro_when_its_creator_is_deleted(): void
    {
        $user = User::factory()->create();
        $macro = RecruitmentMacro::factory()->for($user, 'createdBy')->create();

        $user->delete();

        $this->assertNull($macro->fresh()->created_by);
    }
}
