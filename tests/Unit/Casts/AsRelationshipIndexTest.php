<?php

namespace Tests\Unit\Casts;

use App\Casts\AsRelationshipIndex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Minimal in-test model used to verify the cast resolves model instances correctly.
 */
class StubRelatable extends Model
{
    protected $table = 'stub_relatables';

    public $timestamps = false;
}

class AsRelationshipIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('stub_relatables', function ($table): void {
            $table->increments('id');
            $table->string('name')->default('stub');
        });
    }

    private function stubModel(): Model
    {
        return $this->createStub(Model::class);
    }

    /*
    |--------------------------------------------------------------------------
    | get()
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function get_returns_empty_collection_for_empty_json_array(): void
    {
        $cast = new AsRelationshipIndex;

        $result = $cast->get($this->stubModel(), 'related_models', '[]', []);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function get_returns_empty_collection_for_null_value(): void
    {
        $cast = new AsRelationshipIndex;

        $result = $cast->get($this->stubModel(), 'related_models', null, []);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function get_returns_collection_keyed_by_name(): void
    {
        $record = StubRelatable::create([]);
        $cast = new AsRelationshipIndex;

        $json = json_encode([
            ['name' => 'thing', 'model' => StubRelatable::class, 'key' => $record->id],
        ]);

        $result = $cast->get($this->stubModel(), 'related_models', $json, []);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->has('thing'));
    }

    #[Test]
    public function get_resolves_each_entry_to_its_eloquent_model_instance(): void
    {
        $record = StubRelatable::create([]);
        $cast = new AsRelationshipIndex;

        $json = json_encode([
            ['name' => 'thing', 'model' => StubRelatable::class, 'key' => $record->id],
        ]);

        $result = $cast->get($this->stubModel(), 'related_models', $json, []);

        $this->assertInstanceOf(StubRelatable::class, $result->get('thing'));
        $this->assertTrue($result->get('thing')->is($record));
    }

    #[Test]
    public function get_resolves_multiple_entries(): void
    {
        $recordA = StubRelatable::create([]);
        $recordB = StubRelatable::create([]);
        $cast = new AsRelationshipIndex;

        $json = json_encode([
            ['name' => 'first', 'model' => StubRelatable::class, 'key' => $recordA->id],
            ['name' => 'second', 'model' => StubRelatable::class, 'key' => $recordB->id],
        ]);

        $result = $cast->get($this->stubModel(), 'related_models', $json, []);

        $this->assertCount(2, $result);
        $this->assertTrue($result->get('first')->is($recordA));
        $this->assertTrue($result->get('second')->is($recordB));
    }

    /*
    |--------------------------------------------------------------------------
    | set()
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function set_returns_json_string_for_valid_array(): void
    {
        $cast = new AsRelationshipIndex;

        $entries = [
            ['name' => 'thing', 'model' => StubRelatable::class, 'key' => 1],
        ];

        $result = $cast->set($this->stubModel(), 'related_models', $entries, []);

        $this->assertIsString($result);
        $decoded = json_decode($result, associative: true);
        $this->assertCount(1, $decoded);
        $this->assertSame('thing', $decoded[0]['name']);
    }

    #[Test]
    public function set_accepts_collection_of_entries(): void
    {
        $cast = new AsRelationshipIndex;

        $entries = collect([
            ['name' => 'thing', 'model' => StubRelatable::class, 'key' => 1],
        ]);

        $result = $cast->set($this->stubModel(), 'related_models', $entries, []);

        $this->assertIsString($result);
        $decoded = json_decode($result, associative: true);
        $this->assertCount(1, $decoded);
    }

    #[Test]
    public function set_encodes_empty_input_to_empty_json_array(): void
    {
        $cast = new AsRelationshipIndex;

        $result = $cast->set($this->stubModel(), 'related_models', [], []);

        $this->assertSame('[]', $result);
    }

    #[Test]
    public function set_throws_when_name_is_missing(): void
    {
        $cast = new AsRelationshipIndex;

        $this->expectException(InvalidArgumentException::class);

        $cast->set($this->stubModel(), 'related_models', [
            ['model' => StubRelatable::class, 'key' => 1],
        ], []);
    }

    #[Test]
    public function set_throws_when_model_is_missing(): void
    {
        $cast = new AsRelationshipIndex;

        $this->expectException(InvalidArgumentException::class);

        $cast->set($this->stubModel(), 'related_models', [
            ['name' => 'thing', 'key' => 1],
        ], []);
    }

    #[Test]
    public function set_throws_when_key_is_missing(): void
    {
        $cast = new AsRelationshipIndex;

        $this->expectException(InvalidArgumentException::class);

        $cast->set($this->stubModel(), 'related_models', [
            ['name' => 'thing', 'model' => StubRelatable::class],
        ], []);
    }
}
