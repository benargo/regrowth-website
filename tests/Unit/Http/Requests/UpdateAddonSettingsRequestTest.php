<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\UpdateAddonSettingsRequest;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

#[Group('platform')]
class UpdateAddonSettingsRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[Test]
    #[Group('authorization')]
    public function authorize_denies_a_user_without_the_officer_dashboard_permission(): void
    {
        $user = User::factory()->withPermissions('update-characters', 'edit-datasets', 'update-warcraft-logs-tags')->create();

        $request = $this->makeRequest(['councillor_ids' => [], 'counted_rank_ids' => [], 'counted_tag_ids' => []], $user);

        $this->assertFalse($request->authorize());
    }

    #[Test]
    #[Group('authorization')]
    public function authorize_allows_an_officer_when_no_sections_are_sent(): void
    {
        $user = User::factory()->withPermissions('view-officer-dashboard')->create();

        $this->assertTrue($this->makeRequest([], $user)->authorize());
    }

    #[Test]
    #[Group('authorization')]
    public function authorize_denies_councillor_changes_without_update_characters(): void
    {
        $user = User::factory()->withPermissions('view-officer-dashboard', 'edit-datasets', 'update-warcraft-logs-tags')->create();

        $this->assertFalse($this->makeRequest(['councillor_ids' => []], $user)->authorize());
    }

    #[Test]
    #[Group('authorization')]
    public function authorize_denies_rank_changes_without_edit_datasets(): void
    {
        $user = User::factory()->withPermissions('view-officer-dashboard', 'update-characters', 'update-warcraft-logs-tags')->create();

        $this->assertFalse($this->makeRequest(['counted_rank_ids' => []], $user)->authorize());
    }

    #[Test]
    #[Group('authorization')]
    public function authorize_denies_tag_changes_without_update_warcraft_logs_tags(): void
    {
        $user = User::factory()->withPermissions('view-officer-dashboard', 'update-characters', 'edit-datasets')->create();

        $this->assertFalse($this->makeRequest(['counted_tag_ids' => []], $user)->authorize());
    }

    #[Test]
    #[Group('authorization')]
    public function authorize_allows_each_section_with_only_its_own_permission(): void
    {
        $sections = [
            'councillor_ids' => 'update-characters',
            'counted_rank_ids' => 'edit-datasets',
            'counted_tag_ids' => 'update-warcraft-logs-tags',
        ];

        foreach ($sections as $key => $permission) {
            $user = User::factory()->withPermissions('view-officer-dashboard', $permission)->create();

            $this->assertTrue($this->makeRequest([$key => []], $user)->authorize(), $key);
        }
    }

    #[Test]
    #[Group('authorization')]
    public function authorize_allows_an_admin_to_change_every_section(): void
    {
        $user = User::factory()->admin()->create();

        $request = $this->makeRequest(['councillor_ids' => [], 'counted_rank_ids' => [], 'counted_tag_ids' => []], $user);

        $this->assertTrue($request->authorize());
    }

    // ==================== rules ====================

    #[Test]
    #[Group('validation')]
    public function rules_require_the_game_version(): void
    {
        $validator = $this->validate([]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('game_version', $validator->errors()->toArray());
    }

    #[Test]
    #[Group('validation')]
    public function rules_reject_an_unknown_game_version(): void
    {
        $validator = $this->validate(['game_version' => 'no-such-version']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('game_version', $validator->errors()->toArray());
    }

    #[Test]
    #[Group('happy-path')]
    public function rules_accept_ids_belonging_to_the_game_version(): void
    {
        $gameVersion = GameVersion::factory()->forGuild()->create();
        $character = Character::factory()->forGameVersion($gameVersion)->withUniqueName()->create();
        $rank = GuildRank::factory()->for($gameVersion)->create();
        $tag = GuildTag::factory()->forGuild($gameVersion->warcraftLogsGuild)->create();

        $validator = $this->validate([
            'game_version' => $gameVersion->slug,
            'councillor_ids' => [$character->id],
            'counted_rank_ids' => [$rank->id],
            'counted_tag_ids' => [$tag->id],
        ]);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    #[Test]
    #[Group('validation')]
    public function rules_reject_ids_belonging_to_another_game_version(): void
    {
        $gameVersion = GameVersion::factory()->forGuild()->create();
        $otherVersion = GameVersion::factory()->forGuild()->create();
        $character = Character::factory()->forGameVersion($otherVersion)->withUniqueName()->create();
        $rank = GuildRank::factory()->for($otherVersion)->create();
        $tag = GuildTag::factory()->forGuild($otherVersion->warcraftLogsGuild)->create();

        $validator = $this->validate([
            'game_version' => $gameVersion->slug,
            'councillor_ids' => [$character->id],
            'counted_rank_ids' => [$rank->id],
            'counted_tag_ids' => [$tag->id],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertEqualsCanonicalizing(
            ['councillor_ids.0', 'counted_rank_ids.0', 'counted_tag_ids.0'],
            array_keys($validator->errors()->toArray()),
        );
    }

    #[Test]
    #[Group('validation')]
    public function rules_reject_non_integer_ids(): void
    {
        $gameVersion = GameVersion::factory()->forGuild()->create();

        $validator = $this->validate([
            'game_version' => $gameVersion->slug,
            'councillor_ids' => ['abc'],
            'counted_rank_ids' => ['abc'],
            'counted_tag_ids' => ['abc'],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertEqualsCanonicalizing(
            ['councillor_ids.0', 'counted_rank_ids.0', 'counted_tag_ids.0'],
            array_keys($validator->errors()->toArray()),
        );
    }

    #[Test]
    #[Group('validation')]
    public function rules_reject_sections_that_are_not_arrays(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $validator = $this->validate([
            'game_version' => $gameVersion->slug,
            'councillor_ids' => 'abc',
            'counted_rank_ids' => 'abc',
            'counted_tag_ids' => 'abc',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertEqualsCanonicalizing(
            ['councillor_ids', 'counted_rank_ids', 'counted_tag_ids'],
            array_keys($validator->errors()->toArray()),
        );
    }

    #[Test]
    #[Group('validation')]
    public function rules_reject_every_tag_when_the_game_version_has_no_warcraft_logs_guild(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $tag = GuildTag::factory()->create();

        $validator = $this->validate([
            'game_version' => $gameVersion->slug,
            'counted_tag_ids' => [$tag->id],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('counted_tag_ids.0', $validator->errors()->toArray());
    }

    #[Test]
    #[Group('happy-path')]
    public function rules_accept_empty_sections(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $validator = $this->validate([
            'game_version' => $gameVersion->slug,
            'councillor_ids' => [],
            'counted_rank_ids' => [],
            'counted_tag_ids' => [],
        ]);

        $this->assertTrue($validator->passes(), implode(' ', $validator->errors()->all()));
    }

    // ==================== gameVersion ====================

    #[Test]
    #[Group('happy-path')]
    public function game_version_returns_the_version_named_by_the_slug(): void
    {
        GameVersion::factory()->create();
        $gameVersion = GameVersion::factory()->create();

        $request = $this->makeRequest(['game_version' => $gameVersion->slug]);

        $this->assertTrue($request->gameVersion()->is($gameVersion));
    }

    #[Test]
    #[Group('error-handling')]
    public function game_version_aborts_with_not_found_when_the_slug_is_unknown(): void
    {
        $request = $this->makeRequest(['game_version' => 'no-such-version']);

        $this->expectException(NotFoundHttpException::class);

        $request->gameVersion();
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $data
     */
    private function makeRequest(array $data, ?User $user = null): UpdateAddonSettingsRequest
    {
        $request = UpdateAddonSettingsRequest::create('/', 'PUT', $data);

        if ($user !== null) {
            $request->setUserResolver(fn (): User => $user);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->makeRequest($data)->rules());
    }
}
