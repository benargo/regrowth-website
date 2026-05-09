<?php

namespace Tests\Unit\Models;

use App\Enums\AffectType;
use App\Models\Spell;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\HasMedia;
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

        $this->assertFillable($model, ['id', 'name', 'type']);
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $model = new Spell;

        $this->assertTrue($model->usesTimestamps());
    }

    #[Test]
    public function it_casts_type_to_affect_type_enum(): void
    {
        $model = new Spell;

        $this->assertArrayHasKey('type', $model->getCasts());
        $this->assertSame(AffectType::class, $model->getCasts()['type']);
    }

    #[Test]
    public function it_can_be_created_with_factory(): void
    {
        $spell = $this->create();

        $this->assertModelExists($spell);
        $this->assertNotEmpty($spell->name);
        $this->assertInstanceOf(AffectType::class, $spell->type);
    }

    #[Test]
    public function it_can_be_created_with_a_name(): void
    {
        $spell = $this->create(['name' => 'Fireball']);

        $this->assertTableHas(['name' => 'Fireball']);
        $this->assertSame('Fireball', $spell->name);
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
    public function it_can_be_created_with_a_type(): void
    {
        $spell = $this->create(['type' => AffectType::Magic]);

        $this->assertTableHas(['type' => AffectType::Magic->value]);
        $this->assertSame(AffectType::Magic, $spell->type);
    }

    #[Test]
    public function it_defaults_type_to_physical(): void
    {
        $spell = $this->create(['name' => 'Strike', 'type' => AffectType::Physical]);

        $this->assertSame(AffectType::Physical, $spell->type);
    }

    #[Test]
    public function it_can_be_mass_assigned(): void
    {
        $spell = Spell::create(['name' => 'Holy Nova', 'type' => AffectType::Magic]);

        $this->assertSame('Holy Nova', $spell->name);
        $this->assertSame(AffectType::Magic, $spell->type);
    }

    #[Test]
    public function it_implements_has_media_interface(): void
    {
        $spell = $this->create();

        $this->assertInstanceOf(HasMedia::class, $spell);
    }

    #[Test]
    public function it_can_add_media(): void
    {
        $spell = $this->create();
        $testFile = storage_path('app/test-image.png');
        file_put_contents($testFile, 'fake image content');

        $spell->addMedia($testFile)->toMediaCollection('default');

        $this->assertNotEmpty($spell->getMedia('default'));
        @unlink($testFile);
    }
}
