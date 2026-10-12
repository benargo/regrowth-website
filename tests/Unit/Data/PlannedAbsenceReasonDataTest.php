<?php

namespace Tests\Unit\Data;

use App\Data\PlannedAbsenceReasonData;
use App\Enums\PlannedAbsenceReason;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ValueError;

#[Group('raiding')]
class PlannedAbsenceReasonDataTest extends TestCase
{
    // ==================== fromReason ====================

    #[Test]
    public function from_reason_carries_label_description_and_icon(): void
    {
        $data = PlannedAbsenceReasonData::fromReason(PlannedAbsenceReason::Holiday);

        $this->assertSame(PlannedAbsenceReason::Holiday, $data->key);
        $this->assertSame('On holiday', $data->label);
        $this->assertSame(PlannedAbsenceReason::Holiday->description(), $data->description);
        $this->assertSame('umbrella-beach', $data->icon);
    }

    #[Test]
    public function it_serialises_the_key_to_the_enum_value(): void
    {
        $this->assertSame([
            'key' => 'On holiday',
            'label' => 'On holiday',
            'description' => PlannedAbsenceReason::Holiday->description(),
            'icon' => 'umbrella-beach',
        ], PlannedAbsenceReasonData::fromReason(PlannedAbsenceReason::Holiday)->toArray());
    }

    // ==================== options ====================

    #[Test]
    public function options_lists_every_case_in_order(): void
    {
        $this->assertSame(
            PlannedAbsenceReason::cases(),
            PlannedAbsenceReasonData::options()->pluck('key')->all(),
        );
    }

    // ==================== cast ====================

    #[Test]
    public function cast_get_returns_reason_data_for_a_stored_value(): void
    {
        $result = $this->caster()->get($this->createStub(Model::class), 'reason', 'On holiday', []);

        $this->assertEquals(PlannedAbsenceReasonData::fromReason(PlannedAbsenceReason::Holiday), $result);
    }

    #[Test]
    public function cast_get_returns_null_for_null(): void
    {
        $this->assertNull($this->caster()->get($this->createStub(Model::class), 'reason', null, []));
    }

    #[Test]
    public function cast_set_stores_the_value_of_an_enum_case(): void
    {
        $this->assertSame('Stuck at work', $this->caster()->set($this->createStub(Model::class), 'reason', PlannedAbsenceReason::Work, []));
    }

    #[Test]
    public function cast_set_stores_the_value_of_reason_data(): void
    {
        $data = PlannedAbsenceReasonData::fromReason(PlannedAbsenceReason::Medical);

        $this->assertSame('Medical recovery', $this->caster()->set($this->createStub(Model::class), 'reason', $data, []));
    }

    #[Test]
    public function cast_set_accepts_a_valid_value_string(): void
    {
        $this->assertSame('Other', $this->caster()->set($this->createStub(Model::class), 'reason', 'Other', []));
    }

    #[Test]
    public function cast_set_rejects_free_text(): void
    {
        $this->expectException(ValueError::class);

        $this->caster()->set($this->createStub(Model::class), 'reason', 'Travelling for work', []);
    }

    private function caster(): CastsAttributes
    {
        return PlannedAbsenceReasonData::castUsing([]);
    }
}
