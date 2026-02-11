<?php

namespace Tests\Feature\Notifications;

use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use App\Models\User;
use App\Notifications\DiscordNotifiable;
use App\Notifications\NewLootCouncilComment;
use App\Services\Blizzard\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Mockery\MockInterface;
use NotificationChannels\Discord\DiscordMessage;
use Tests\TestCase;

class NewLootCouncilCommentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();
        Notification::fake();
    }

    protected function mockItemService(): void
    {
        $this->instance(
            ItemService::class,
            Mockery::mock(ItemService::class, function (MockInterface $mock) {
                $mock->shouldReceive('find')
                    ->andReturnUsing(fn (int $id) => [
                        'id' => $id,
                        'name' => "Test Item {$id}",
                    ]);

                $mock->shouldReceive('media')
                    ->andReturn([
                        'assets' => [
                            ['key' => 'icon', 'value' => 'https://example.com/icon.jpg'],
                        ],
                    ]);
            })
        );
    }

    protected function createItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        return Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
    }

    // ==========================================
    // Notification dispatch tests
    // ==========================================

    public function test_notification_is_sent_when_new_comment_is_created(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $this->actingAs($user)->post(route('loot.items.comments.store', $item), [
            'body' => 'This is a new comment',
        ]);

        Notification::assertSentTo(
            new DiscordNotifiable('lootcouncil'),
            NewLootCouncilComment::class,
        );
    }

    public function test_notification_is_not_sent_when_comment_is_updated(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Original comment',
        ]);

        $this->actingAs($user)->put(route('loot.comments.update', [$item, $comment]), [
            'body' => 'Updated comment',
        ]);

        Notification::assertNotSentTo(
            new DiscordNotifiable('lootcouncil'),
            NewLootCouncilComment::class,
        );
    }

    public function test_notification_is_not_sent_when_comment_is_resolved(): void
    {
        $item = $this->createItem();
        $officer = User::factory()->officer()->create();
        $raider = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $raider->id,
        ]);

        $this->actingAs($officer)->put(route('loot.comments.update', [$item, $comment]), [
            'isResolved' => true,
        ]);

        Notification::assertNotSentTo(
            new DiscordNotifiable('lootcouncil'),
            NewLootCouncilComment::class,
        );
    }

    // ==========================================
    // Notification content tests
    // ==========================================

    public function test_notification_contains_correct_embed_structure(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Test comment body',
        ]);
        $comment->load(['user', 'item']);

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toDiscord(new DiscordNotifiable('lootcouncil'));

        $this->assertInstanceOf(DiscordMessage::class, $message);
        $this->assertEquals('New comment received', $message->embed['title']);
        $this->assertEquals(5814783, $message->embed['color']);
        $this->assertNotEmpty($message->embed['url']);
        $this->assertNotEmpty($message->embed['timestamp']);
    }

    public function test_notification_description_includes_user_mention_and_item_name(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'My comment body',
        ]);
        $comment->load(['user', 'item']);

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toDiscord(new DiscordNotifiable('lootcouncil'));

        $this->assertStringContainsString("<@{$user->id}>", $message->embed['description']);
        $this->assertStringContainsString("**Test Item {$item->id}**", $message->embed['description']);
        $this->assertStringContainsString('My comment body', $message->embed['description']);
    }

    public function test_notification_includes_resolve_button_component(): void
    {
        $this->markTestSkipped('Resolve button is commented out');

        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
        ]);
        $comment->load(['user', 'item']);

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toDiscord(new DiscordNotifiable('lootcouncil'));

        $this->assertNotEmpty($message->components);
        $actionRow = $message->components[0];
        $this->assertEquals(1, $actionRow['type']); // Action Row
        $button = $actionRow['components'][0];
        $this->assertEquals(2, $button['type']); // Button
        $this->assertEquals('Resolve', $button['label']);
        $this->assertEquals("resolve_lc_comment:{$comment->id}", $button['custom_id']);
    }

    public function test_notification_embed_links_to_item_page(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        $comment = Comment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
        ]);
        $comment->load(['user', 'item']);

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toDiscord(new DiscordNotifiable('lootcouncil'));

        $expectedUrl = route('loot.items.show', [
            'item' => $item->id,
            'name' => 'test-item-'.$item->id,
        ]);
        $this->assertEquals($expectedUrl, $message->embed['url']);
    }
}
