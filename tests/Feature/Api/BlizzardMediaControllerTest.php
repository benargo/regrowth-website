<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Blizzard\BlizzardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlizzardMediaControllerTest extends TestCase
{
    use RefreshDatabase;

    private function blizzardPage(int $page, int $totalPages, array $results): array
    {
        return ['results' => $results, 'pageCount' => $totalPages, 'page' => $page];
    }

    private function blizzardResult(string $url): array
    {
        return ['data' => ['assets' => [['value' => $url]]]];
    }

    // ─── authentication ────────────────────────────────────────────────────────

    #[Test]
    public function it_returns_401_when_unauthenticated(): void
    {
        $this->getJson(route('api.blizzard.media'))->assertUnauthorized();
    }

    // ─── page fetching & caching ───────────────────────────────────────────────

    #[Test]
    public function it_fetches_all_blizzard_pages_and_returns_combined_results(): void
    {
        $user = User::factory()->create();
        $tagCount = count(BlizzardService::VALID_MEDIA_TAGS);

        $this->mock(BlizzardService::class, function (MockInterface $mock) use ($tagCount) {
            $mock->shouldReceive('cacheKey')->with('blizzard:icons:all')->andReturn('blizzard:icons:all:test');

            // One call per tag, each returning a single result
            $mock->shouldReceive('searchMedia')
                ->times($tagCount)
                ->andReturnUsing(fn (array $params) => $this->blizzardPage(1, 1, [
                    $this->blizzardResult("https://example.com/icons/56/{$params['tags']}_spell.jpg"),
                ]));
        });

        $response = $this->actingAs($user)->getJson(route('api.blizzard.media'));

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'name', 'url']], 'last_page', 'current_page']);
        $response->assertJsonCount($tagCount, 'data');
        $response->assertJsonPath('current_page', 1);
    }

    #[Test]
    public function it_caches_icons_and_does_not_re_fetch_blizzard_on_second_request(): void
    {
        $user = User::factory()->create();
        $tagCount = count(BlizzardService::VALID_MEDIA_TAGS);

        Cache::flush();

        $callCount = 0;

        $this->mock(BlizzardService::class, function (MockInterface $mock) use (&$callCount) {
            $mock->shouldReceive('cacheKey')->with('blizzard:icons:all')->andReturn('blizzard:icons:all:test');

            $mock->shouldReceive('searchMedia')->andReturnUsing(function () use (&$callCount) {
                $callCount++;

                return $this->blizzardPage(1, 1, [
                    $this->blizzardResult('https://example.com/icons/56/spell_holy_avenging.jpg'),
                ]);
            });
        });

        $this->actingAs($user)->getJson(route('api.blizzard.media'))->assertOk();
        $this->actingAs($user)->getJson(route('api.blizzard.media'))->assertOk();

        $this->assertSame($tagCount, $callCount, 'Blizzard API should only be called once per tag; second request must use cache.');
    }

    // ─── null url filtering ────────────────────────────────────────────────────

    #[Test]
    public function it_skips_results_with_null_url(): void
    {
        $user = User::factory()->create();
        $tagCount = count(BlizzardService::VALID_MEDIA_TAGS);

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cacheKey')->with('blizzard:icons:all')->andReturn('blizzard:icons:all:test');

            $mock->shouldReceive('searchMedia')
                ->andReturnUsing(function (array $params) {
                    if ($params['tags'] === BlizzardService::VALID_MEDIA_TAGS[0]) {
                        return $this->blizzardPage(1, 1, [
                            ['data' => ['assets' => [['value' => null]]]],
                            $this->blizzardResult('https://example.com/icons/56/spell_fire_blast.jpg'),
                        ]);
                    }

                    return $this->blizzardPage(1, 1, []);
                });
        });

        $response = $this->actingAs($user)->getJson(route('api.blizzard.media'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'spell_fire_blast');
    }

    // ─── deduplication ─────────────────────────────────────────────────────────

    #[Test]
    public function it_deduplicates_icons_with_the_same_url_derived_name_across_pages(): void
    {
        $user = User::factory()->create();
        $duplicateUrl = 'https://example.com/icons/56/spell_holy_avenging.jpg';

        $this->mock(BlizzardService::class, function (MockInterface $mock) use ($duplicateUrl) {
            $mock->shouldReceive('cacheKey')->with('blizzard:icons:all')->andReturn('blizzard:icons:all:test');

            // Each tag returns the same icon URL on two pages
            $mock->shouldReceive('searchMedia')
                ->andReturnUsing(fn (array $params) => match ($params['_page']) {
                    1 => $this->blizzardPage(1, 2, [$this->blizzardResult($duplicateUrl)]),
                    default => $this->blizzardPage(2, 2, [$this->blizzardResult($duplicateUrl)]),
                });
        });

        $response = $this->actingAs($user)->getJson(route('api.blizzard.media'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.url', $duplicateUrl);
    }

    // ─── name filtering ────────────────────────────────────────────────────────

    #[Test]
    public function it_filters_results_by_partial_name_match(): void
    {
        $user = User::factory()->create();

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cacheKey')->with('blizzard:icons:all')->andReturn('blizzard:icons:all:test');

            $mock->shouldReceive('searchMedia')
                ->andReturnUsing(function (array $params) {
                    if ($params['tags'] === BlizzardService::VALID_MEDIA_TAGS[0]) {
                        return $this->blizzardPage(1, 1, [
                            $this->blizzardResult('https://example.com/icons/56/spell_holy_avenging.jpg'),
                            $this->blizzardResult('https://example.com/icons/56/spell_fire_blast.jpg'),
                            $this->blizzardResult('https://example.com/icons/56/spell_holy_light.jpg'),
                        ]);
                    }

                    return $this->blizzardPage(1, 1, []);
                });
        });

        $response = $this->actingAs($user)->getJson(route('api.blizzard.media', ['name' => 'holy']));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    // ─── id is a slug ──────────────────────────────────────────────────────────

    #[Test]
    public function it_returns_the_filename_slug_as_the_icon_id(): void
    {
        $user = User::factory()->create();

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cacheKey')->with('blizzard:icons:all')->andReturn('blizzard:icons:all:test');

            $mock->shouldReceive('searchMedia')
                ->andReturnUsing(function (array $params) {
                    if ($params['tags'] === BlizzardService::VALID_MEDIA_TAGS[0]) {
                        return $this->blizzardPage(1, 1, [
                            $this->blizzardResult('https://example.com/icons/56/Spell_Holy_Avenging.jpg'),
                        ]);
                    }

                    return $this->blizzardPage(1, 1, []);
                });
        });

        $response = $this->actingAs($user)->getJson(route('api.blizzard.media'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', 'spell-holy-avenging');
        $response->assertJsonPath('data.0.name', 'Spell_Holy_Avenging');
    }

    // ─── pagination ────────────────────────────────────────────────────────────

    #[Test]
    public function it_paginates_results_at_1000_per_page(): void
    {
        $user = User::factory()->create();

        $manyResults = collect(range(1, 1250))
            ->map(fn (int $i) => $this->blizzardResult("https://example.com/icons/56/spell_{$i}.jpg"))
            ->all();

        $this->mock(BlizzardService::class, function (MockInterface $mock) use ($manyResults) {
            $mock->shouldReceive('cacheKey')->with('blizzard:icons:all')->andReturn('blizzard:icons:all:test');

            $mock->shouldReceive('searchMedia')
                ->andReturnUsing(function (array $params) use ($manyResults) {
                    if ($params['tags'] === BlizzardService::VALID_MEDIA_TAGS[0]) {
                        return $this->blizzardPage(1, 1, $manyResults);
                    }

                    return $this->blizzardPage(1, 1, []);
                });
        });

        $page1 = $this->actingAs($user)->getJson(route('api.blizzard.media', ['page' => 1]));
        $page1->assertOk();
        $page1->assertJsonCount(1000, 'data');
        $page1->assertJsonPath('last_page', 2);
        $page1->assertJsonPath('current_page', 1);

        $page2 = $this->actingAs($user)->getJson(route('api.blizzard.media', ['page' => 2]));
        $page2->assertOk();
        $page2->assertJsonCount(250, 'data');
        $page2->assertJsonPath('current_page', 2);
    }
}
