<?php

namespace App\Providers;

use App\Services\Attendance\Calculator;
use App\Services\Attendance\DataTable;
use App\Services\Attendance\FiltersData;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class AttendanceServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Calculator::class, function ($app) {
            return new Calculator;
        });

        $this->app->bind(DataTable::class, function ($app) {
            return new DataTable(
                $app->make(Calculator::class),
                new FiltersData
            );
        });

        $this->app->when(DataTable::class)->needs(Calculator::class)->give(function ($app) {
            return $app->make(Calculator::class);
        });

        $this->app->when(DataTable::class)->needs(FiltersData::class)->give(function ($app) {
            return new FiltersData;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            Calculator::class,
            DataTable::class,
        ];
    }
}
