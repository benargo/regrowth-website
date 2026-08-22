<?php

namespace Tests\Unit\Models;

use App\Casts\AsBinaryColor;
use App\Enums\RaidBackground;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('raiding')]
class RaidTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Raid::class;
    }

    #[Test]
    public function it_uses_raids_table(): void
    {
        $model = new Raid;

        $this->assertSame('raids', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_id(): void
    {
        $model = new Raid;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Raid;

        $this->assertFillable($model, [
            'name',
            'difficulty',
            'background_css_class',
            'color',
            'phase_id',
            'max_players',
            'max_loot_councillors',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new Raid;

        $this->assertFillableAttribute($model, [
            'name',
            'difficulty',
            'background_css_class',
            'color',
            'phase_id',
            'max_players',
            'max_loot_councillors',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new Raid;

        $this->assertCasts($model, [
            'background_css_class' => RaidBackground::class,
            'color' => AsBinaryColor::class,
            'max_players' => 'integer',
            'max_loot_councillors' => 'integer',
        ]);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $phase = Phase::factory()->create();

        $raid = $this->create([
            'name' => 'Karazhan',
            'difficulty' => 'Normal',
            'phase_id' => $phase->id,
            'max_players' => 10,
            'max_loot_councillors' => 3,
        ]);

        $this->assertTableHas(['name' => 'Karazhan']);
        $this->assertModelExists($raid);
    }

    // ==================== background_css_class ====================

    #[Test]
    public function background_css_class_is_nullable(): void
    {
        $raid = $this->create(['background_css_class' => null]);

        $this->assertNull($raid->background_css_class);
        $this->assertModelExists($raid);
    }

    #[Test]
    public function background_css_class_is_cast_to_raid_background_enum(): void
    {
        $raid = $this->create(['background_css_class' => RaidBackground::Karazhan]);

        $this->assertInstanceOf(RaidBackground::class, $raid->background_css_class);
        $this->assertSame(RaidBackground::Karazhan, $raid->background_css_class);
    }

    // ==================== color ====================

    #[Test]
    public function color_is_nullable(): void
    {
        $raid = $this->create(['color' => null]);

        $this->assertNull($raid->color);
        $this->assertModelExists($raid);
    }

    #[Test]
    public function color_getter_returns_hex_string(): void
    {
        $raid = $this->create(['color' => '8b7ed0']);

        $this->assertSame('8b7ed0', $raid->color);
    }

    #[Test]
    public function color_setter_accepts_hex_string_without_hash(): void
    {
        $raid = $this->create(['color' => '8b7ed0']);

        $this->assertSame('8b7ed0', $raid->color);
    }

    #[Test]
    public function color_setter_accepts_hex_string_with_hash(): void
    {
        $raid = $this->create(['color' => '#8b7ed0']);

        $this->assertSame('8b7ed0', $raid->color);
    }

    #[Test]
    public function color_setter_accepts_integer_hex_literal(): void
    {
        $raid = $this->create(['color' => 0x8B7ED0]);

        $this->assertSame('8b7ed0', $raid->color);
    }

    #[Group('error-handling')]
    #[Test]
    public function color_setter_throws_for_invalid_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->create(['color' => 'not-a-color']);
    }

    // ==================== max_players ====================

    #[Test]
    public function max_players_is_nullable(): void
    {
        $raid = $this->create(['max_players' => null]);

        $this->assertNull($raid->max_players);
        $this->assertModelExists($raid);
    }

    // ==================== max_loot_councillors ====================

    #[Test]
    public function max_loot_councillors_is_nullable(): void
    {
        $raid = $this->create(['max_loot_councillors' => null]);

        $this->assertNull($raid->max_loot_councillors);
        $this->assertModelExists($raid);
    }

    // ==================== max_groups ====================

    #[Test]
    public function it_calculates_max_groups_from_max_players(): void
    {
        $raid = $this->create(['max_players' => 20]);

        $this->assertSame(4, $raid->max_groups);
    }

    #[Test]
    public function ten_player_raid_has_two_max_groups(): void
    {
        $raid = $this->factory()->tenPlayer()->create();

        $this->assertSame(2, $raid->max_groups);
    }

    #[Test]
    public function twenty_five_player_raid_has_five_max_groups(): void
    {
        $raid = $this->factory()->twentyFivePlayer()->create();

        $this->assertSame(5, $raid->max_groups);
    }

    #[Test]
    public function max_groups_rounds_up_for_non_divisible_player_counts(): void
    {
        $raid = $this->create(['max_players' => 11]);

        $this->assertSame(3, $raid->max_groups);
    }

    #[Test]
    public function max_groups_is_not_persisted_to_the_database(): void
    {
        $raid = $this->create(['max_players' => 10]);

        $this->assertArrayNotHasKey('max_groups', $raid->getAttributes());
        $this->assertSame(2, $raid->max_groups);
    }

    // ==================== slug ====================

    #[Test]
    public function it_generates_a_slug_from_the_name(): void
    {
        $raid = $this->create(['name' => 'Serpentshrine Cavern']);

        $this->assertSame('serpentshrine-cavern', $raid->slug);
    }

    #[Test]
    public function it_generates_a_slug_with_special_characters_removed(): void
    {
        $raid = $this->create(['name' => "Magtheridon's Lair"]);

        $this->assertSame('magtheridons-lair', $raid->slug);
    }

    #[Test]
    public function slug_is_not_persisted_to_the_database(): void
    {
        $raid = $this->create(['name' => 'Karazhan']);

        $this->assertArrayNotHasKey('slug', $raid->getAttributes());
        $this->assertSame('karazhan', $raid->slug);
    }

    // ==================== factory ====================

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $raid = $this->create();

        $this->assertNotEmpty($raid->name);
        $this->assertNotEmpty($raid->difficulty);
        $this->assertNotNull($raid->phase_id);
        $this->assertModelExists($raid);
    }

    #[Test]
    public function factory_with_loot_councillors_state_sets_max_loot_councillors(): void
    {
        $raid = $this->factory()->withLootCouncillors(5)->create();

        $this->assertSame(5, $raid->max_loot_councillors);
    }

    #[Test]
    public function factory_ten_player_state_sets_max_players_to_ten(): void
    {
        $raid = $this->factory()->tenPlayer()->create();

        $this->assertSame(10, $raid->max_players);
    }

    #[Test]
    public function factory_twenty_five_player_state_sets_max_players_to_twenty_five(): void
    {
        $raid = $this->factory()->twentyFivePlayer()->create();

        $this->assertSame(25, $raid->max_players);
    }

    #[Test]
    public function factory_normal_state_sets_difficulty_to_normal(): void
    {
        $raid = $this->factory()->normal()->create();

        $this->assertSame('Normal', $raid->difficulty);
    }

    #[Test]
    public function factory_heroic_state_sets_difficulty_to_heroic(): void
    {
        $raid = $this->factory()->heroic()->create();

        $this->assertSame('Heroic', $raid->difficulty);
    }

    #[Test]
    public function factory_with_bosses_state_creates_bosses(): void
    {
        $raid = $this->factory()->withBosses(3)->create();

        $this->assertCount(3, $raid->bosses);
        $this->assertTrue($raid->bosses->every(fn (Boss $boss) => $boss->raid_id === $raid->id));
    }

    #[Test]
    public function factory_with_comments_state_creates_comments_through_items(): void
    {
        $raid = $this->factory()->withComments(2)->create();

        $this->assertCount(1, $raid->items);
        $this->assertCount(2, $raid->comments);
    }

    // ==================== phase ====================

    #[Test]
    public function it_belongs_to_a_phase(): void
    {
        $phase = Phase::factory()->create();
        $raid = $this->create(['phase_id' => $phase->id]);

        $this->assertRelation($raid, 'phase', BelongsTo::class);
        $this->assertTrue($raid->phase->is($phase));
    }

    // ==================== bosses ====================

    #[Test]
    public function it_has_many_bosses(): void
    {
        $raid = $this->create();
        Boss::factory()->count(3)->create(['raid_id' => $raid->id]);

        $this->assertRelation($raid, 'bosses', HasMany::class);
        $this->assertCount(3, $raid->bosses);
    }

    // ==================== items ====================

    #[Test]
    public function it_belongs_to_many_items(): void
    {
        $raid = $this->factory()->withItems(2)->create();

        $this->assertRelation($raid, 'items', BelongsToMany::class);
        $this->assertCount(2, $raid->items);
        $this->assertInstanceOf(Item::class, $raid->items->first());
    }

    #[Test]
    public function it_has_many_trash_items(): void
    {
        $raid = $this->factory()->withItems(2)->create();
        Item::factory()->fromBoss(Boss::factory()->create(['raid_id' => $raid->id]))->create();

        $this->assertRelation($raid, 'trashItems', BelongsToMany::class);
        $this->assertCount(2, $raid->trashItems);
        $this->assertTrue($raid->trashItems->every(fn (Item $item) => $item->boss_id === null));
    }

    #[Test]
    public function factory_with_items_state_creates_trash_items(): void
    {
        $raid = $this->factory()->withItems(3)->create();

        $this->assertCount(3, $raid->items);
        $this->assertTrue($raid->items->every(fn (Item $item) => $item->boss_id === null));
    }

    #[Test]
    public function an_item_can_belong_to_two_raids(): void
    {
        $hyjal = $this->create(['name' => 'Hyjal Summit']);
        $blackTemple = $this->create(['name' => 'Black Temple']);
        $item = Item::factory()->trashDrop()->create();

        $item->raids()->attach([$hyjal->id, $blackTemple->id]);

        $this->assertCount(1, $hyjal->trashItems);
        $this->assertCount(1, $blackTemple->trashItems);
        $this->assertSame($item->id, $hyjal->trashItems->first()->id);
        $this->assertSame($item->id, $blackTemple->trashItems->first()->id);
    }

    // ==================== comments ====================

    #[Test]
    public function it_has_many_comments_through_its_items(): void
    {
        $raid = $this->factory()->withComments(2)->create();

        $this->assertRelation($raid, 'comments', HasMany::class);
        $this->assertCount(2, $raid->comments);
        $this->assertInstanceOf(Comment::class, $raid->comments->first());
    }

    #[Test]
    public function comments_are_scoped_to_item_commentable_type(): void
    {
        $raid = $this->factory()->withComments(2)->create();
        $item = $raid->items->first();

        // Insert a comment with a different commentable_type pointing at the same item ID
        Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => User::class,
        ]);

        $this->assertCount(2, $raid->comments);
    }

    // ==================== events ====================

    #[Test]
    public function events_returns_belongs_to_many_relationship(): void
    {
        $model = new Raid;

        $this->assertInstanceOf(BelongsToMany::class, $model->events());
    }

    #[Test]
    public function it_can_attach_events(): void
    {
        $raid = $this->create();
        $event = Event::factory()->create();

        $raid->events()->attach($event->id);

        $this->assertCount(1, $raid->events);
        $this->assertSame($event->id, $raid->events->first()->id);
    }

    #[Test]
    public function events_returns_empty_collection_when_none_attached(): void
    {
        $raid = $this->create();

        $this->assertCount(0, $raid->events);
    }
}
