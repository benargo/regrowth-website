<?php

namespace Tests\Unit\Providers;

use App\Providers\AttendanceServiceProvider;
use App\Services\Attendance\Calculator;
use App\Services\Attendance\DataTable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceServiceProviderTest extends TestCase
{
    // ==================== provides ====================

    #[Test]
    public function it_declares_the_expected_services_in_provides(): void
    {
        $provider = new AttendanceServiceProvider($this->app);
        $provides = $provider->provides();

        $this->assertContains(Calculator::class, $provides);
        $this->assertContains(DataTable::class, $provides);
    }

    // ==================== contextual binding ====================

    #[Test]
    public function it_registers_contextual_calculator_binding_for_data_table(): void
    {
        $provider = new AttendanceServiceProvider($this->app);
        $provider->register();

        $this->assertInstanceOf(DataTable::class, $this->app->make(DataTable::class));
    }
}
