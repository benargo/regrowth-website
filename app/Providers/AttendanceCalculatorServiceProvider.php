<?php

namespace App\Providers;

use App\Services\AttendanceCalculator\Aggregators\ReportsAggregator;
use App\Services\AttendanceCalculator\AttendanceCalculator;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class AttendanceCalculatorServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            ReportsAggregator::class,
            AttendanceCalculator::class,
        ];
    }
}
