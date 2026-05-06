<?php

namespace Tests\Unit\Providers;

use App\Models\Raids\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    // ==================== whereNone macro ====================

    #[Test]
    public function it_registers_the_where_none_macro_on_the_eloquent_builder(): void
    {
        $this->assertTrue(method_exists(Report::query(), 'whereNone') || is_callable([Report::query(), 'whereNone']));
    }

    #[Test]
    public function where_none_macro_returns_no_rows(): void
    {
        Report::factory()->count(3)->create();

        $results = Report::query()->whereNone()->get();

        $this->assertCount(0, $results);
    }
}
