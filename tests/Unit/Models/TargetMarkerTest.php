<?php

namespace Tests\Unit\Models;

use App\Models\TargetMarker;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class TargetMarkerTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return TargetMarker::class;
    }

    #[Test]
    public function it_uses_target_markers_table(): void
    {
        $model = new TargetMarker;

        $this->assertSame('target_markers', $model->getTable());
    }

    #[Test]
    public function it_uses_slug_as_primary_key(): void
    {
        $model = new TargetMarker;

        $this->assertSame('slug', $model->getKeyName());
    }

    #[Test]
    public function it_does_not_use_auto_incrementing_primary_key(): void
    {
        $model = new TargetMarker;

        $this->assertFalse($model->getIncrementing());
    }

    #[Test]
    public function it_uses_string_key_type(): void
    {
        $model = new TargetMarker;

        $this->assertSame('string', $model->getKeyType());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new TargetMarker;

        $this->assertFillable($model, ['slug', 'name']);
    }

    #[Test]
    public function it_can_be_created_with_slug_and_name(): void
    {
        $marker = $this->create(['slug' => 'skull', 'name' => 'Skull']);

        $this->assertTableHas(['slug' => 'skull', 'name' => 'Skull']);
        $this->assertModelExists($marker);
    }

    #[Test]
    public function it_uses_slug_as_the_model_key(): void
    {
        $marker = $this->create(['slug' => 'star', 'name' => 'Star']);

        $this->assertSame('star', $marker->getKey());
    }

    #[Test]
    public function it_can_be_retrieved_by_slug(): void
    {
        $this->create(['slug' => 'circle', 'name' => 'Circle']);

        $found = TargetMarker::find('circle');

        $this->assertNotNull($found);
        $this->assertSame('Circle', $found->name);
    }

    #[Test]
    public function it_enforces_unique_slug_constraint(): void
    {
        $this->create(['slug' => 'moon', 'name' => 'Moon']);

        $this->assertUniqueConstraint(function () {
            $this->create(['slug' => 'moon', 'name' => 'Duplicate']);
        });
    }

    #[Test]
    public function it_does_not_have_timestamps(): void
    {
        $model = new TargetMarker;

        $this->assertFalse($model->usesTimestamps());
    }

    #[Test]
    public function it_can_be_mass_assigned(): void
    {
        $marker = TargetMarker::create(['slug' => 'diamond', 'name' => 'Diamond']);

        $this->assertSame('diamond', $marker->slug);
        $this->assertSame('Diamond', $marker->name);
    }
}
