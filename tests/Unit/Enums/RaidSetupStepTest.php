<?php

namespace Tests\Unit\Enums;

use App\Enums\RaidSetupStep;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class RaidSetupStepTest extends TestCase
{
    #[Test]
    public function it_lists_the_bosses_step_with_its_label_and_component(): void
    {
        $this->assertSame(
            [['value' => 'bosses', 'label' => 'Bosses', 'component' => 'BossesSection']],
            RaidSetupStep::options(),
        );
    }

    #[Test]
    public function it_starts_and_ends_the_wizard_at_the_bosses_step(): void
    {
        $this->assertSame(RaidSetupStep::Bosses, RaidSetupStep::first());
        $this->assertNull(RaidSetupStep::Bosses->next());
        $this->assertNull(RaidSetupStep::Bosses->previous());
    }

    #[Test]
    public function it_has_a_section_component_file_for_every_step(): void
    {
        foreach (RaidSetupStep::cases() as $step) {
            $this->assertFileExists(resource_path("js/Components/Raids/{$step->component()}.jsx"));
        }
    }
}
