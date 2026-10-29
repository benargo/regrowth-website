<?php

namespace Tests\Feature\Addons;

use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Support\MediaLibrary\UnknownBlizzardIcon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('platform')]
class LootToolOverviewTest extends TestCase
{
    use RefreshDatabase;

    private const string ICON_PATH = 'blizzard-cdn/icons/56/inv_jewelry_necklace_29naxxramas.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    // ==================== overview ====================

    #[Group('happy-path')]
    #[Test]
    public function overview_is_public(): void
    {
        Saloon::fake([FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200)]);

        $this->get(route('addons.loot-tool.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Addons/LootTool/Overview'));
    }

    // ==================== addon links ====================

    #[Group('happy-path')]
    #[Test]
    public function it_passes_the_addon_links_from_config(): void
    {
        config([
            'guild.loot-tool.curseforge_url' => 'https://curseforge.test/loot-tool',
            'guild.loot-tool.github_url' => 'https://github.test/loot-tool',
        ]);
        Saloon::fake([FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200)]);

        $this->get(route('addons.loot-tool.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('curseforgeUrl', 'https://curseforge.test/loot-tool')
                ->where('githubUrl', 'https://github.test/loot-tool'));
    }

    // ==================== item icon ====================

    #[Group('blizzard-integration')]
    #[Group('happy-path')]
    #[Test]
    public function it_fetches_and_stores_the_item_icon_when_it_is_not_in_storage(): void
    {
        Saloon::fake([FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200)]);

        $this->get(route('addons.loot-tool.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('itemIconUrl', Storage::disk('s3')->url(self::ICON_PATH)));

        Saloon::assertSent(FetchIconRequest::class);
        Storage::disk('s3')->assertExists(self::ICON_PATH);
    }

    #[Group('blizzard-integration')]
    #[Group('happy-path')]
    #[Test]
    public function it_serves_the_item_icon_from_storage_without_calling_blizzard(): void
    {
        Storage::disk('s3')->put(self::ICON_PATH, 'BINARY');
        Saloon::fake([FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200)]);

        $this->get(route('addons.loot-tool.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('itemIconUrl', Storage::disk('s3')->url(self::ICON_PATH)));

        Saloon::assertNothingSent();
    }

    #[Group('blizzard-integration')]
    #[Group('error-handling')]
    #[Test]
    public function it_falls_back_to_the_question_mark_icon_when_blizzard_has_no_icon(): void
    {
        Saloon::fake([FetchIconRequest::class => MockResponse::make(body: '', status: 404)]);

        $this->get(route('addons.loot-tool.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('itemIconUrl', app(UnknownBlizzardIcon::class)->url()));
    }

    #[Group('blizzard-integration')]
    #[Group('error-handling')]
    #[Test]
    public function it_falls_back_to_the_question_mark_icon_when_blizzard_fails(): void
    {
        Saloon::fake([FetchIconRequest::class => MockResponse::make(body: '', status: 500)]);

        $this->get(route('addons.loot-tool.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('itemIconUrl', app(UnknownBlizzardIcon::class)->url()));

        Storage::disk('s3')->assertMissing(self::ICON_PATH);
    }
}
