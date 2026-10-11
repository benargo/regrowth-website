<?php

namespace Tests\Unit\Enums;

use App\Enums\GameVersionSetupStep;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('platform')]
class GameVersionSetupStepTest extends TestCase
{
    // ==================== options() ====================

    #[Test]
    public function it_lists_every_step_in_wizard_order_with_its_label_and_component(): void
    {
        $this->assertSame(
            [
                ['value' => 'theme', 'label' => 'Theme', 'component' => 'ThemeSection'],
                ['value' => 'races-and-classes', 'label' => 'Races and classes', 'component' => 'RacesAndClassesSection'],
                ['value' => 'phases', 'label' => 'Phases and raids', 'component' => 'PhasesSection'],
                ['value' => 'guild-ranks', 'label' => 'Guild ranks', 'component' => 'GuildRanksSection'],
            ],
            GameVersionSetupStep::options(),
        );
    }

    // ==================== first() ====================

    #[Test]
    public function it_starts_the_wizard_at_the_first_case(): void
    {
        $this->assertSame(GameVersionSetupStep::Theme, GameVersionSetupStep::first());
    }

    // ==================== next() ====================

    #[Test]
    #[TestWith(['theme', 'races-and-classes'])]
    #[TestWith(['races-and-classes', 'phases'])]
    #[TestWith(['phases', 'guild-ranks'])]
    public function it_returns_the_step_after_the_given_step(string $step, string $expected): void
    {
        $this->assertSame(GameVersionSetupStep::from($expected), GameVersionSetupStep::from($step)->next());
    }

    #[Test]
    public function it_returns_no_step_after_the_last_step(): void
    {
        $this->assertNull(GameVersionSetupStep::GUILD_RANKS->next());
    }

    // ==================== previous() ====================

    #[Test]
    #[TestWith(['races-and-classes', 'theme'])]
    #[TestWith(['phases', 'races-and-classes'])]
    #[TestWith(['guild-ranks', 'phases'])]
    public function it_returns_the_step_before_the_given_step(string $step, string $expected): void
    {
        $this->assertSame(GameVersionSetupStep::from($expected), GameVersionSetupStep::from($step)->previous());
    }

    #[Test]
    public function it_returns_no_step_before_the_first_step(): void
    {
        $this->assertNull(GameVersionSetupStep::Theme->previous());
    }

    // ==================== component() ====================

    #[Test]
    #[DataProvider('steps')]
    public function it_has_a_section_component_file_for_every_step(GameVersionSetupStep $step): void
    {
        $this->assertFileExists(resource_path("js/Components/GameVersions/{$step->component()}.jsx"));
    }

    // ==================== helpers ====================

    /**
     * @return array<string, array{GameVersionSetupStep}>
     */
    public static function steps(): array
    {
        return collect(GameVersionSetupStep::cases())
            ->mapWithKeys(fn (GameVersionSetupStep $step): array => [$step->value => [$step]])
            ->all();
    }
}
