<?php

namespace Tests\Feature;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\Blizzard\BlizzardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\Downloaders\HttpFacadeDownloader;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SpellControllerTest extends TestCase
{
    use RefreshDatabase;

    protected DiscordRole $editorRole;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->editorRole = DiscordRole::create([
            'id' => '829022020301094923',
            'name' => 'Editor',
            'position' => 2,
            'is_visible' => true,
        ]);
        $this->editorRole->givePermissionTo(Permission::firstOrCreate(['name' => 'edit-datasets', 'guard_name' => 'web']));
    }

    // ─── media() ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_returns_spell_icon_list_from_blizzard(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->editorRole->id);

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('searchMedia')
                ->once()
                ->withArgs(fn ($params) => in_array('spell', $params['tags'] ?? []))
                ->andReturn([
                    'results' => [
                        [
                            'data' => [
                                'id' => 101,
                                'tags' => ['spell_holy_avenginwrath'],
                                'assets' => [
                                    ['value' => 'https://example.com/icon1.jpg'],
                                ],
                            ],
                        ],
                    ],
                    'pageCount' => 1,
                    'page' => 1,
                ]);
        });

        $response = $this->actingAs($user)->getJson(route('spells.media'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [['id', 'name', 'url']],
            'total_pages',
            'current_page',
        ]);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', 101);
        $response->assertJsonPath('data.0.url', 'https://example.com/icon1.jpg');
    }

    #[Test]
    public function it_forwards_name_query_to_blizzard_media_search(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->editorRole->id);

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('searchMedia')
                ->once()
                ->withArgs(fn ($params) => isset($params['name']) && $params['name'] === 'holy')
                ->andReturn(['results' => [], 'pageCount' => 1, 'page' => 1]);
        });

        $this->actingAs($user)->getJson(route('spells.media', ['name' => 'holy']))->assertOk();
    }

    #[Test]
    public function it_returns_401_on_media_when_unauthenticated(): void
    {
        $response = $this->getJson(route('spells.media'));

        $response->assertUnauthorized();
    }

    // ─── store() ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_spell_and_downloads_its_icon(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->editorRole->id);

        Http::fake(['*' => Http::response('fake-image-data', 200)]);
        config(['media-library.media_downloader' => HttpFacadeDownloader::class]);

        $response = $this->actingAs($user)->postJson(route('spells.store'), [
            'name' => 'Avenging Wrath',
            'type' => 'Magic',
            'icon_url' => 'https://example.com/icon.jpg',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('spells', ['name' => 'Avenging Wrath', 'type' => 'Magic']);
        $response->assertJsonPath('name', 'Avenging Wrath');
    }

    #[Test]
    public function it_returns_403_on_store_without_edit_datasets_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('spells.store'), [
            'name' => 'Avenging Wrath',
            'type' => 'Magic',
            'icon_url' => 'https://example.com/icon.jpg',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function it_returns_422_when_name_is_missing_on_store(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->editorRole->id);

        $response = $this->actingAs($user)->postJson(route('spells.store'), [
            'type' => 'Magic',
            'icon_url' => 'https://example.com/icon.jpg',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    #[Test]
    public function it_returns_422_when_type_is_invalid_on_store(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->editorRole->id);

        $response = $this->actingAs($user)->postJson(route('spells.store'), [
            'name' => 'Avenging Wrath',
            'type' => 'InvalidType',
            'icon_url' => 'https://example.com/icon.jpg',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('type');
    }
}
