<?php

namespace App\Providers;

use App\Services\AttendanceCalculator\Aggregators\ReportsAggregator;
use App\Services\AttendanceCalculator\AttendanceCalculator;
use App\Services\AttendanceCalculator\AttendanceMatrix;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class AttendanceCalculatorServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ReportsAggregator::class, function () {
            return new ReportsAggregator;
        });

        $this->app->singleton(AttendanceCalculator::class, function ($app) {
            return new AttendanceCalculator($app->make(ReportsAggregator::class), config('app.timezone'));
        });

        $this->app->singleton(AttendanceMatrix::class, function ($app) {
            return new AttendanceMatrix($app->make(AttendanceCalculator::class), config('app.timezone'));
        });
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
            AttendanceMatrix::class,
        ];
    }
}
