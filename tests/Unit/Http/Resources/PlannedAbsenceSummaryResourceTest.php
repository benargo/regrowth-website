<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\PlannedAbsenceReason;
use App\Http\Resources\PlannedAbsenceSummaryResource;
use App\Models\PlannedAbsence;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class PlannedAbsenceSummaryResourceTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_emits_only_the_reason_label_and_icon_and_the_notes(): void
    {
        $absence = PlannedAbsence::factory()->withoutParents()->make([
            'reason' => PlannedAbsenceReason::Work,
            'notes' => 'Late shift.',
        ]);

        $array = (new PlannedAbsenceSummaryResource($absence))->resolve(new Request);

        $this->assertSame([
            'reason' => ['label' => 'Stuck at work', 'icon' => 'briefcase'],
            'notes' => 'Late shift.',
        ], $array);
    }
}
