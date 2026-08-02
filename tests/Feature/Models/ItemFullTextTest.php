<?php

namespace Tests\Feature\Models;

use App\Models\Item;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FullTextTestCase;

#[Group('loot')]
class ItemFullTextTest extends FullTextTestCase
{
    #[Test]
    public function matching_name_matches_case_insensitively(): void
    {
        $this->withCommittedTransaction(
            create: fn () => ['match' => Item::factory()->withName('Archbishop\'s Slippers')->create()],
            assert: function (array $items) {
                $results = Item::query()->matchingName('ARCHBISHOP')->get();

                $this->assertCount(1, $results);
                $this->assertTrue($results->contains('id', $items['match']->id));
            },
        );
    }

    #[Test]
    public function matching_name_matches_a_partial_name(): void
    {
        $this->withCommittedTransaction(
            create: fn () => [
                'match' => Item::factory()->withName('Archbishop\'s Slippers')->create(),
                'decoy' => Item::factory()->withName('Thunderfury')->create(),
            ],
            assert: function (array $items) {
                $results = Item::query()->matchingName('slipper')->get();

                $this->assertCount(1, $results);
                $this->assertTrue($results->contains('id', $items['match']->id));
            },
        );
    }

    #[Test]
    public function matching_name_excludes_items_with_a_null_name(): void
    {
        Item::factory()->create();

        $this->assertCount(0, Item::query()->matchingName('anything')->get());
    }
}
