<?php

namespace Tests\Feature\Data\Items;

use App\Data\Items\PriorityStatsRowData;
use App\Models\LootPriority;
use App\Support\MediaLibrary\UnknownBlizzardIcon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class PriorityStatsRowDataTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_the_questionmark_icon_when_no_icon_is_attached(): void
    {
        Storage::fake('s3');
        $model = LootPriority::factory()->create();

        $data = PriorityStatsRowData::fromPriority($model, [1 => 0]);

        $this->assertSame(app(UnknownBlizzardIcon::class)->url(), $data->icon);
    }
}
