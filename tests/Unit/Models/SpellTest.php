<?php

namespace Tests\Unit\Models;

use App\Models\Spell;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class SpellTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Spell::class;
    }

    #[Test]
    public function it_uses_spells_table(): void
    {
        $model = new Spell;

        $this->assertSame('spells', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_integer_primary_key(): void
    {
        $model = new Spell;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
        $this->assertSame('int', $model->getKeyType());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Spell;

        $this->assertFillable($model, ['id', 'name', 'icon_url']);
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $model = new Spell;

        $this->assertTrue($model->usesTimestamps());
    }

    #[Test]
    public function it_can_be_created_with_factory(): void
    {
        $spell = $this->create();

        $this->assertModelExists($spell);
        $this->assertNotEmpty($spell->name);
    }

    #[Test]
    public function it_can_be_created_with_a_name(): void
    {
        $spell = $this->create(['name' => 'Fireball']);

        $this->assertTableHas(['name' => 'Fireball']);
        $this->assertSame('Fireball', $spell->name);
    }

    #[Test]
    public function it_can_be_created_with_an_icon_url(): void
    {
        $spell = $this->create(['name' => 'Frostbolt', 'icon_url' => 'https://example.com/frostbolt.png']);

        $this->assertTableHas(['name' => 'Frostbolt', 'icon_url' => 'https://example.com/frostbolt.png']);
        $this->assertSame('https://example.com/frostbolt.png', $spell->icon_url);
    }

    #[Test]
    public function it_allows_null_icon_url(): void
    {
        $spell = $this->create(['name' => 'Shadow Bolt', 'icon_url' => null]);

        $this->assertModelExists($spell);
        $this->assertNull($spell->icon_url);
    }

    #[Test]
    public function it_can_be_retrieved_by_id(): void
    {
        $spell = $this->create(['name' => 'Arcane Missiles']);

        $found = Spell::find($spell->id);

        $this->assertNotNull($found);
        $this->assertSame('Arcane Missiles', $found->name);
    }

    #[Test]
    public function it_can_be_mass_assigned(): void
    {
        $spell = Spell::create(['name' => 'Holy Nova', 'icon_url' => null]);

        $this->assertSame('Holy Nova', $spell->name);
        $this->assertNull($spell->icon_url);
    }
}
