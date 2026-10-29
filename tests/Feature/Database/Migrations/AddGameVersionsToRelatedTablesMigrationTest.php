<?php

namespace Tests\Feature\Database\Migrations;

use App\Models\Comment;
use App\Models\DiscordNotification;
use App\Models\Item;
use App\Models\Raid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

#[Group('platform')]
#[Group('loot')]
class AddGameVersionsToRelatedTablesMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function up_repoints_item_comments_from_the_blizzard_id_to_the_item_uuid(): void
    {
        $item = Item::factory()->create();
        $comment = Comment::factory()->create(['commentable_id' => $item->id, 'commentable_type' => Item::class]);
        DB::table('comments')->where('id', $comment->id)->update(['commentable_id' => (string) $item->blizzard_id]);

        $this->remapItemMorphReferences();

        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'commentable_type' => Item::class, 'commentable_id' => $item->id]);
    }

    #[Test]
    public function up_repoints_item_media_from_the_blizzard_id_to_the_item_uuid(): void
    {
        $item = Item::factory()->create();
        $mediaId = $this->insertMedia(Item::class, (string) $item->blizzard_id);

        $this->remapItemMorphReferences();

        $this->assertDatabaseHas('media', ['id' => $mediaId, 'model_type' => Item::class, 'model_id' => $item->id]);
    }

    #[Test]
    public function up_repoints_item_discord_notification_links_from_the_blizzard_id_to_the_item_uuid(): void
    {
        $item = Item::factory()->create();
        $notification = DiscordNotification::factory()->create();
        $linkId = DB::table('discord_notification_related_models')->insertGetId([
            'discord_notification_id' => $notification->id,
            'model_type' => Item::class,
            'model_id' => (string) $item->blizzard_id,
        ]);

        $this->remapItemMorphReferences();

        $this->assertDatabaseHas('discord_notification_related_models', ['id' => $linkId, 'model_type' => Item::class, 'model_id' => $item->id]);
    }

    #[Test]
    public function up_leaves_references_to_other_models_with_a_matching_id_untouched(): void
    {
        $item = Item::factory()->create();
        $mediaId = $this->insertMedia(Raid::class, (string) $item->blizzard_id);

        $this->remapItemMorphReferences();

        $this->assertDatabaseHas('media', ['id' => $mediaId, 'model_type' => Raid::class, 'model_id' => (string) $item->blizzard_id]);
    }

    // ==================== helpers ====================

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_09_104402_add_game_versions_to_related_tables.php');
    }

    /**
     * Run the migration's item morph remap step against the current data.
     */
    private function remapItemMorphReferences(): void
    {
        $migration = $this->migration();

        (new ReflectionMethod($migration, 'remapItemMorphReferencesToUuid'))->invoke($migration);
    }

    private function insertMedia(string $modelType, string $modelId): int
    {
        return DB::table('media')->insertGetId([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'collection_name' => 'icon',
            'name' => 'icon',
            'file_name' => 'icon.jpg',
            'disk' => 'public',
            'size' => 1,
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'generated_conversions' => '[]',
            'responsive_images' => '[]',
        ]);
    }
}
