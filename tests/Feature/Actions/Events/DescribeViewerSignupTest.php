<?php

namespace Tests\Feature\Actions\Events;

use App\Actions\Events\DescribeViewerSignup;
use App\Enums\PlannedAbsenceReason;
use App\Enums\SignupStatus;
use App\Models\Character;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use App\Models\PlannedAbsence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class DescribeViewerSignupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Character $character;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();

        $this->user = User::factory()->create();
        $this->character = Character::factory()->for($this->user)->create();
        $this->event = Event::factory()->live()->create([
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);
    }

    // ==================== absences ====================

    #[Test]
    public function a_planned_absence_gives_its_reason(): void
    {
        PlannedAbsence::factory()->create([
            'user_id' => $this->user->id,
            'reason' => PlannedAbsenceReason::Holiday,
            'start_date' => now(),
            'end_date' => now()->addDays(2),
        ]);

        $this->assertLine("You're on holiday", 'umbrella-beach');
    }

    #[Test]
    public function a_character_only_absence_counts(): void
    {
        PlannedAbsence::factory()->withoutUser()->create([
            'character_id' => $this->character->id,
            'reason' => PlannedAbsenceReason::Work,
            'start_date' => now(),
            'end_date' => now()->addDays(2),
        ]);

        $this->assertLine("You're stuck at work", 'briefcase');
    }

    #[Test]
    public function an_absence_beats_a_cancelled_signup(): void
    {
        PlannedAbsence::factory()->create([
            'user_id' => $this->user->id,
            'reason' => PlannedAbsenceReason::Other,
            'start_date' => now(),
            'end_date' => now()->addDays(2),
        ]);
        $this->signUp(['signup_status' => SignupStatus::Cancelled]);

        $this->assertLine("You're away", 'portal-exit');
    }

    #[Test]
    public function an_absence_that_ends_before_the_raid_is_ignored(): void
    {
        PlannedAbsence::factory()->create([
            'user_id' => $this->user->id,
            'start_date' => now()->subDays(3),
            'end_date' => now(),
        ]);

        $this->assertLine("You haven't signed up yet.");
    }

    #[Test]
    public function someone_elses_absence_is_ignored(): void
    {
        PlannedAbsence::factory()->create([
            'start_date' => now(),
            'end_date' => now()->addDays(2),
        ]);

        $this->assertLine("You haven't signed up yet.");
    }

    // ==================== signups ====================

    #[Test]
    public function a_cancelled_signup_says_they_cant_make_it(): void
    {
        $this->signUp(['signup_status' => SignupStatus::Cancelled]);

        $this->assertLine("You said you can't make it.");
    }

    #[Test]
    public function an_unconfirmed_signup_counts_as_signed_up(): void
    {
        $this->signUp(['signup_status' => SignupStatus::Unconfirmed]);

        $this->assertLine("You're signed up.");
    }

    #[Test]
    public function a_signup_without_a_posted_comp_is_signed_up(): void
    {
        $this->signUp(['slot_number' => 3]);

        $this->assertLine("You're signed up.");
    }

    #[Test]
    public function no_signup_says_they_havent_signed_up(): void
    {
        $this->assertLine("You haven't signed up yet.");
    }

    // ==================== comp ====================

    #[Test]
    public function a_slotted_signup_with_a_posted_comp_is_in_the_team(): void
    {
        $this->signUp(['slot_number' => 3]);
        $this->postComp();

        $this->assertLine("You're in the team.");
    }

    #[Test]
    public function any_slotted_character_puts_them_in_the_team(): void
    {
        $second = Character::factory()->for($this->user)->create();
        $this->event->characters()->attach($second, ['signup_status' => SignupStatus::Confirmed]);
        $this->signUp(['slot_number' => 3]);
        $this->postComp();

        $this->assertLine("You're in the team.");
    }

    #[Test]
    public function a_benched_signup_with_a_posted_comp_is_on_the_bench(): void
    {
        $this->signUp(['slot_number' => 3, 'is_benched' => true]);
        $this->postComp();

        $this->assertLine("You're on the bench.");
    }

    #[Test]
    public function an_unslotted_signup_with_a_posted_comp_is_on_the_bench(): void
    {
        $this->signUp();
        EventAssignment::factory()->for($this->event)->create();

        $this->assertLine("You're on the bench.");
    }

    /**
     * @param  array<string, mixed>  $pivot
     */
    private function signUp(array $pivot = []): void
    {
        $this->event->characters()->attach($this->character, $pivot + ['signup_status' => SignupStatus::Confirmed]);
    }

    private function postComp(): void
    {
        EventAssignmentGroup::factory()->for($this->event)->create();
    }

    private function assertLine(string $label, ?string $icon = null): void
    {
        $line = DescribeViewerSignup::run($this->event, $this->user);

        $this->assertSame($label, $line->label);
        $this->assertSame($icon, $line->icon);
    }
}
