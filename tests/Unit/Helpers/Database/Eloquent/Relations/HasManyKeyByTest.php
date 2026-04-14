<?php

namespace Tests\Unit\Helpers\Database\Eloquent\Relations;

use App\Helpers\Database\Eloquent\Relations\HasManyKeyBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RelationParentModel extends Model
{
    protected $table = 'relation_test_parents';

    protected $guarded = [];

    public $timestamps = false;
}

class RelationChildModel extends Model
{
    protected $table = 'relation_test_children';

    protected $guarded = [];

    public $timestamps = false;
}

class HasManyKeyByTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('relation_test_parents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('relation_test_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relation_parent_model_id');
            $table->string('code');
            $table->string('label');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('relation_test_children');
        Schema::dropIfExists('relation_test_parents');
        parent::tearDown();
    }

    private function makeRelation(string $keyBy, RelationParentModel $parent): HasManyKeyBy
    {
        $instance = new RelationChildModel;

        return new HasManyKeyBy(
            $keyBy,
            $instance->newQuery(),
            $parent,
            'relation_test_children.relation_parent_model_id',
            'id'
        );
    }

    #[Test]
    public function it_extends_has_many(): void
    {
        $parent = RelationParentModel::create(['name' => 'Parent']);

        $this->assertInstanceOf(HasMany::class, $this->makeRelation('code', $parent));
    }

    #[Test]
    public function get_results_returns_collection_keyed_by_the_specified_field(): void
    {
        $parent = RelationParentModel::create(['name' => 'Parent']);
        RelationChildModel::create(['relation_parent_model_id' => $parent->id, 'code' => 'alpha', 'label' => 'Alpha']);
        RelationChildModel::create(['relation_parent_model_id' => $parent->id, 'code' => 'beta', 'label' => 'Beta']);

        $results = $this->makeRelation('code', $parent)->getResults();

        $this->assertArrayHasKey('alpha', $results);
        $this->assertArrayHasKey('beta', $results);
        $this->assertSame('Alpha', $results['alpha']->label);
        $this->assertSame('Beta', $results['beta']->label);
    }

    #[Test]
    public function get_results_returns_empty_collection_when_parent_has_no_children(): void
    {
        $parent = RelationParentModel::create(['name' => 'Parent']);

        $results = $this->makeRelation('code', $parent)->getResults();

        $this->assertCount(0, $results);
    }

    #[Test]
    public function get_results_only_returns_children_belonging_to_the_parent(): void
    {
        $parent1 = RelationParentModel::create(['name' => 'Parent 1']);
        $parent2 = RelationParentModel::create(['name' => 'Parent 2']);
        RelationChildModel::create(['relation_parent_model_id' => $parent1->id, 'code' => 'alpha', 'label' => 'Alpha']);
        RelationChildModel::create(['relation_parent_model_id' => $parent2->id, 'code' => 'beta', 'label' => 'Beta']);

        $results = $this->makeRelation('code', $parent1)->getResults();

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('alpha', $results);
        $this->assertArrayNotHasKey('beta', $results);
    }

    #[Test]
    public function get_results_can_key_by_a_different_field(): void
    {
        $parent = RelationParentModel::create(['name' => 'Parent']);
        RelationChildModel::create(['relation_parent_model_id' => $parent->id, 'code' => 'alpha', 'label' => 'First']);
        RelationChildModel::create(['relation_parent_model_id' => $parent->id, 'code' => 'beta', 'label' => 'Second']);

        $results = $this->makeRelation('label', $parent)->getResults();

        $this->assertArrayHasKey('First', $results);
        $this->assertArrayHasKey('Second', $results);
        $this->assertSame('alpha', $results['First']->code);
    }
}
