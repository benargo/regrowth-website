<?php

namespace Tests\Unit\Enums;

use App\Enums\LootPriorityType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class LootPriorityTypeTest extends TestCase
{
    #[Test]
    public function it_has_the_expected_backing_values(): void
    {
        $this->assertSame('Role', LootPriorityType::ROLE->value);
        $this->assertSame('Class', LootPriorityType::CLASS_TYPE->value);
        $this->assertSame('Spec', LootPriorityType::SPEC->value);
        $this->assertSame('Custom', LootPriorityType::CUSTOM->value);
        $this->assertSame('Meme', LootPriorityType::MEME->value);
    }

    #[Test]
    public function map_returns_case_names_keyed_to_their_values(): void
    {
        $this->assertSame([
            'ROLE' => 'Role',
            'CLASS_TYPE' => 'Class',
            'SPEC' => 'Spec',
            'CUSTOM' => 'Custom',
            'MEME' => 'Meme',
        ], LootPriorityType::map());
    }

    #[Test]
    public function sort_order_ranks_role_before_class_before_spec_before_custom_before_meme(): void
    {
        $this->assertSame(0, LootPriorityType::ROLE->sortOrder());
        $this->assertSame(1, LootPriorityType::CLASS_TYPE->sortOrder());
        $this->assertSame(2, LootPriorityType::SPEC->sortOrder());
        $this->assertSame(3, LootPriorityType::CUSTOM->sortOrder());
        $this->assertSame(4, LootPriorityType::MEME->sortOrder());
    }
}
