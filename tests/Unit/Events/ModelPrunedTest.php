<?php

namespace Tests\Unit\Events;

use App\Events\ModelPruned;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelPrunedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_holds_a_reference_to_the_pruned_model(): void
    {
        $event = Event::factory()->create();

        $appEvent = new ModelPruned($event);

        $this->assertTrue($appEvent->model->is($event));
    }
}
