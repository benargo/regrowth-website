<?php

namespace Tests\Unit\Enums;

use App\Enums\AssignmentType;
use App\Models\Character;
use App\Models\PlayableClass;
use App\Models\Spell;
use App\Models\TargetMarker;
use PHPUnit\Framework\TestCase;

class AssignmentTypeTest extends TestCase
{
    public function test_has_all_expected_cases(): void
    {
        $cases = AssignmentType::cases();
        $values = array_map(fn ($case) => $case->value, $cases);

        $this->assertContains('character', $values);
        $this->assertContains('playable_class', $values);
        $this->assertContains('spell', $values);
        $this->assertContains('target_marker', $values);
        $this->assertCount(4, $cases);
    }

    public function test_can_be_created_from_string_value(): void
    {
        $this->assertSame(AssignmentType::Character, AssignmentType::from('character'));
        $this->assertSame(AssignmentType::PlayableClass, AssignmentType::from('playable_class'));
        $this->assertSame(AssignmentType::Spell, AssignmentType::from('spell'));
        $this->assertSame(AssignmentType::TargetMarker, AssignmentType::from('target_marker'));
    }

    public function test_returns_correct_model_class_for_character(): void
    {
        $this->assertSame(Character::class, AssignmentType::Character->modelClass());
    }

    public function test_returns_correct_model_class_for_playable_class(): void
    {
        $this->assertSame(PlayableClass::class, AssignmentType::PlayableClass->modelClass());
    }

    public function test_returns_correct_model_class_for_spell(): void
    {
        $this->assertSame(Spell::class, AssignmentType::Spell->modelClass());
    }

    public function test_returns_correct_model_class_for_target_marker(): void
    {
        $this->assertSame(TargetMarker::class, AssignmentType::TargetMarker->modelClass());
    }
}
