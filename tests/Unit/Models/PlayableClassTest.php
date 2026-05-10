<?php

namespace Tests\Unit\Models;

use App\Models\PlayableClass;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class PlayableClassTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return PlayableClass::class;
    }

    #[Test]
    public function it_uses_playable_classes_table(): void
    {
        $model = new PlayableClass;

        $this->assertSame('playable_classes', $model->getTable());
    }

    #[Test]
    public function it_does_not_use_auto_incrementing_primary_key(): void
    {
        $model = new PlayableClass;

        $this->assertSame('id', $model->getKeyName());
        $this->assertFalse($model->getIncrementing());
    }

    #[Test]
    public function it_does_not_have_timestamps(): void
    {
        $model = new PlayableClass;

        $this->assertFalse($model->usesTimestamps());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new PlayableClass;

        $this->assertFillable($model, [
            'id',
            'name',
        ]);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $playableClass = $this->create(['name' => 'Warrior']);

        $this->assertTableHas(['name' => 'Warrior']);
        $this->assertModelExists($playableClass);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $playableClass = $this->create();

        $this->assertNotEmpty($playableClass->name);
        $this->assertModelExists($playableClass);
    }

    // slug

    #[Test]
    public function slug_returns_kebab_case_of_name(): void
    {
        $playableClass = new PlayableClass(['name' => 'Death Knight']);

        $this->assertSame('death-knight', $playableClass->slug);
    }

    #[Test]
    public function slug_returns_lowercase_single_word_name(): void
    {
        $playableClass = new PlayableClass(['name' => 'Warrior']);

        $this->assertSame('warrior', $playableClass->slug);
    }

    // characters

    #[Test]
    public function characters_returns_has_many_relationship(): void
    {
        $playableClass = new PlayableClass;

        $this->assertInstanceOf(HasMany::class, $playableClass->characters());
    }

    #[Test]
    public function characters_returns_empty_collection_when_none_associated(): void
    {
        $playableClass = $this->create();

        $this->assertCount(0, $playableClass->characters);
    }
}
