<?php

namespace Tests\Unit\Helpers\Database\Eloquent\Traits;

use App\Helpers\Database\Eloquent\Relations\HasManyKeyBy as HasManyKeyByRelation;
use App\Helpers\Database\Eloquent\Traits\HasManyKeyBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TraitParentModel extends Model
{
    use HasManyKeyBy;

    protected $table = 'trait_test_parents';

    protected $guarded = [];

    public $timestamps = false;

    public function children(): HasManyKeyByRelation
    {
        return $this->hasManyKeyBy('code', TraitChildModel::class);
    }

    public function childrenKeyedById(): HasManyKeyByRelation
    {
        return $this->hasManyKeyBy('id', TraitChildModel::class);
    }
}

class TraitChildModel extends Model
{
    protected $table = 'trait_test_children';

    protected $guarded = [];

    public $timestamps = false;
}

class HasManyKeyByTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('trait_test_parents', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('trait_test_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trait_parent_model_id');
            $table->string('code');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('trait_test_children');
        Schema::dropIfExists('trait_test_parents');
        parent::tearDown();
    }

    #[Test]
    public function has_many_key_by_returns_the_correct_relation_type(): void
    {
        $parent = TraitParentModel::create([]);

        $this->assertInstanceOf(HasManyKeyByRelation::class, $parent->children());
    }

    #[Test]
    public function has_many_key_by_infers_the_foreign_key_from_the_model_class_name(): void
    {
        $parent = TraitParentModel::create([]);

        $this->assertSame(
            'trait_test_children.trait_parent_model_id',
            $parent->children()->getQualifiedForeignKeyName()
        );
    }

    #[Test]
    public function relation_returns_results_keyed_by_the_specified_field(): void
    {
        $parent = TraitParentModel::create([]);
        TraitChildModel::create(['trait_parent_model_id' => $parent->id, 'code' => 'foo']);
        TraitChildModel::create(['trait_parent_model_id' => $parent->id, 'code' => 'bar']);

        $results = $parent->children;

        $this->assertArrayHasKey('foo', $results);
        $this->assertArrayHasKey('bar', $results);
    }

    #[Test]
    public function relation_returns_empty_collection_when_parent_has_no_children(): void
    {
        $parent = TraitParentModel::create([]);

        $this->assertCount(0, $parent->children);
    }

    #[Test]
    public function relation_can_key_by_a_different_field(): void
    {
        $parent = TraitParentModel::create([]);
        $child1 = TraitChildModel::create(['trait_parent_model_id' => $parent->id, 'code' => 'foo']);
        $child2 = TraitChildModel::create(['trait_parent_model_id' => $parent->id, 'code' => 'bar']);

        $results = $parent->childrenKeyedById;

        $this->assertArrayHasKey($child1->id, $results);
        $this->assertArrayHasKey($child2->id, $results);
    }
}
