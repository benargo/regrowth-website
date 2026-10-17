<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\ScopedResourceCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Resources\PreservedScopedTestResource;
use Tests\Support\Resources\ScopedTestResource;
use Tests\TestCase;

#[Group('platform')]
class ScopedResourceCollectionTest extends TestCase
{
    #[Test]
    public function collection_returns_a_scoped_resource_collection(): void
    {
        $this->assertInstanceOf(ScopedResourceCollection::class, ScopedTestResource::collection([$this->thrall()]));
    }

    #[Test]
    public function it_applies_the_scope_to_every_item(): void
    {
        $this->assertSame(
            [['id' => 1, 'name' => 'Thrall'], ['id' => 2, 'name' => 'Jaina']],
            ScopedTestResource::collection([$this->thrall(), $this->jaina()])->scope('summary')->resolve(new Request),
        );
    }

    #[Test]
    public function it_scopes_collections_built_with_to_resource_collection(): void
    {
        $collection = collect([$this->thrall()])->toResourceCollection(ScopedTestResource::class);

        $this->assertSame('s', $collection->scope('detail')->resolve(new Request)[0]['secret']);
    }

    #[Test]
    public function it_resolves_an_empty_scoped_collection_to_an_empty_array(): void
    {
        $this->assertSame([], ScopedTestResource::collection([])->scope('summary')->resolve(new Request));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_logs_an_undefined_key_once_for_the_whole_collection(): void
    {
        Log::spy();

        $array = ScopedTestResource::collection([$this->thrall(), $this->jaina()])->scope('typo')->resolve(new Request);

        $this->assertArrayHasKey('secret', $array[0]);
        Log::shouldHaveReceived('error')->once()->with('Scope [typo] is not defined on ['.ScopedTestResource::class.'].');
    }

    #[Test]
    public function it_keeps_preserved_keys_when_scoped(): void
    {
        $array = PreservedScopedTestResource::collection(['x' => $this->thrall(), 'y' => $this->jaina()])
            ->scope('summary')
            ->resolve(new Request);

        $this->assertSame(['x', 'y'], array_keys($array));
    }

    /**
     * @return array{id: int, name: string, secret: string}
     */
    private function thrall(): array
    {
        return ['id' => 1, 'name' => 'Thrall', 'secret' => 's'];
    }

    /**
     * @return array{id: int, name: string, secret: string}
     */
    private function jaina(): array
    {
        return ['id' => 2, 'name' => 'Jaina', 'secret' => 's'];
    }
}
