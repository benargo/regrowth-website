<?php

namespace App\Providers;

use App\Services\Attendance\Aggregators\ReportsAggregator;
use App\Services\Attendance\Calculators\GuildAttendanceCalculator;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class RegrowthAttendanceServiceProvider extends ServiceProvider implements DeferrableProvider
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
            GuildAttendanceCalculator::class,
        ];
    }
}
