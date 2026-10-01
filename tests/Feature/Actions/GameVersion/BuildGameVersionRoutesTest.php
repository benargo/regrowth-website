<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\BuildGameVersionRoutes;
use App\Enums\GameVersionSetupStep;
use App\Models\GameVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class BuildGameVersionRoutesTest extends TestCase
{
    // ==================== steps ====================

    #[Group('happy-path')]
    #[Test]
    public function it_links_each_setup_step_to_its_setup_page_in_wizard_order(): void
    {
        $gameVersion = $this->gameVersion();

        $steps = BuildGameVersionRoutes::make()->steps($gameVersion);

        $this->assertSame([
            [
                'value' => 'races-and-classes',
                'label' => 'Races and classes',
                'component' => 'RacesAndClassesSection',
                'href' => 'http://localhost/manage/game-versions/7/setup/races-and-classes',
            ],
            [
                'value' => 'phases',
                'label' => 'Phases and raids',
                'component' => 'PhasesSection',
                'href' => 'http://localhost/manage/game-versions/7/setup/phases',
            ],
            [
                'value' => 'guild-ranks',
                'label' => 'Guild ranks',
                'component' => 'GuildRanksSection',
                'href' => 'http://localhost/manage/game-versions/7/setup/guild-ranks',
            ],
        ], $steps);
    }

    #[Test]
    public function it_leaves_the_step_links_out_before_the_game_version_exists(): void
    {
        $steps = BuildGameVersionRoutes::make()->steps();

        $this->assertSame(['races-and-classes', 'phases', 'guild-ranks'], array_column($steps, 'value'));
        $this->assertSame([], array_column($steps, 'href'));
    }

    // ==================== pages ====================

    #[Test]
    public function it_links_the_index_page_to_the_create_page(): void
    {
        $routes = BuildGameVersionRoutes::make()->forIndex();

        $this->assertSame(['create' => 'http://localhost/manage/game-versions/create'], $routes);
    }

    #[Test]
    public function it_gives_the_create_page_its_cancel_and_store_urls(): void
    {
        $routes = BuildGameVersionRoutes::make()->forCreate();

        $this->assertSame([
            'index' => 'http://localhost/manage/game-versions',
            'store' => 'http://localhost/manage/game-versions',
        ], $routes);
    }

    #[Test]
    public function it_gives_the_edit_page_its_index_update_and_review_urls(): void
    {
        $routes = BuildGameVersionRoutes::make()->forEdit($this->gameVersion());

        $this->assertSame([
            'index' => 'http://localhost/manage/game-versions',
            'update' => 'http://localhost/manage/game-versions/7',
            'review' => 'http://localhost/manage/game-versions/7/review',
        ], $routes);
    }

    #[Test]
    public function it_gives_the_review_page_its_finish_details_and_own_urls(): void
    {
        $routes = BuildGameVersionRoutes::make()->forReview($this->gameVersion());

        $this->assertSame([
            'index' => 'http://localhost/manage/game-versions',
            'edit' => 'http://localhost/manage/game-versions/7/edit',
            'review' => 'http://localhost/manage/game-versions/7/review',
        ], $routes);
    }

    #[Test]
    public function it_links_a_listed_game_version_to_its_edit_and_destroy_urls(): void
    {
        $links = BuildGameVersionRoutes::make()->links($this->gameVersion());

        $this->assertSame([
            'edit' => 'http://localhost/manage/game-versions/7/edit',
            'destroy' => 'http://localhost/manage/game-versions/7',
        ], $links);
    }

    // ==================== forSetup ====================

    /**
     * @return array<string, array{GameVersionSetupStep, bool, string, string}>
     */
    public static function setupNeighbours(): array
    {
        return [
            'first step goes back to the details' => [
                GameVersionSetupStep::RACES_AND_CLASSES,
                false,
                'http://localhost/manage/game-versions/7/edit',
                'http://localhost/manage/game-versions/7/setup/phases',
            ],
            'middle step links both neighbours' => [
                GameVersionSetupStep::PHASES,
                false,
                'http://localhost/manage/game-versions/7/setup/races-and-classes',
                'http://localhost/manage/game-versions/7/setup/guild-ranks',
            ],
            'last step continues to the review' => [
                GameVersionSetupStep::GUILD_RANKS,
                false,
                'http://localhost/manage/game-versions/7/setup/phases',
                'http://localhost/manage/game-versions/7/review',
            ],
            'step opened from the review returns there' => [
                GameVersionSetupStep::RACES_AND_CLASSES,
                true,
                'http://localhost/manage/game-versions/7/edit',
                'http://localhost/manage/game-versions/7/review',
            ],
        ];
    }

    #[DataProvider('setupNeighbours')]
    #[Test]
    public function it_gives_a_setup_step_its_back_and_onward_urls(
        GameVersionSetupStep $step,
        bool $returnToReview,
        string $previous,
        string $next,
    ): void {
        $routes = BuildGameVersionRoutes::make()->forSetup($this->gameVersion(), $step, $returnToReview);

        $this->assertSame([
            'update' => 'http://localhost/manage/game-versions/7',
            'edit' => 'http://localhost/manage/game-versions/7/edit',
            'review' => 'http://localhost/manage/game-versions/7/review',
            'previous' => $previous,
            'next' => $next,
        ], $routes);
    }

    // ==================== helpers ====================

    /**
     * An unsaved game version with a known id: building its URLs only needs
     * the route key, not a database row.
     */
    private function gameVersion(): GameVersion
    {
        return GameVersion::factory()->make(['id' => 7]);
    }
}
