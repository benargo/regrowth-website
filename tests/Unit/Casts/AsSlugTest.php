<?php

namespace Tests\Unit\Casts;

use App\Casts\AsSlug;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class AsSlugTest extends TestCase
{
    #[Test]
    public function get_returns_the_stored_value(): void
    {
        $cast = new AsSlug;
        $model = $this->createStub(Model::class);

        $result = $cast->get($model, 'slug', 'the-burning-crusade', []);

        $this->assertSame('the-burning-crusade', $result);
    }

    #[Test]
    public function set_converts_the_value_to_a_slug(): void
    {
        $cast = new AsSlug;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'slug', 'tHe Burning CruSade', []);

        $this->assertSame('the-burning-crusade', $result);
    }

    #[Test]
    public function set_passes_null_through(): void
    {
        $cast = new AsSlug;
        $model = $this->createStub(Model::class);

        $result = $cast->set($model, 'slug', null, []);

        $this->assertNull($result);
    }
}
