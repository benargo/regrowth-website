<?php

namespace App\Providers;

use App\Services\Attendance\Calculator;
use App\Services\Attendance\Dashboard;
use App\Services\Attendance\DataTable;
use App\Services\Attendance\Filters;
use App\Services\Attendance\Graphs;
use App\Services\Attendance\Matrix;
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
            return new Calculator(config('app.timezone'));
        });

        $this->app->singleton(Dashboard::class, function ($app) {
            return new Dashboard(
                $app->make(Calculator::class),
                $app->make(DataTable::class),
            );
        });

        $this->app->bind(DataTable::class, function ($app) {
            return new DataTable(
                $app->make(Calculator::class),
                new Filters
            );
        });

        $this->app->when(DataTable::class)->needs(Calculator::class)->give(function ($app) {
            return $app->make(Calculator::class);
        });

        $this->app->when(DataTable::class)->needs(Filters::class)->give(function ($app) {
            return new Filters;
        });

        $this->app->singleton(Matrix::class, function ($app) {
            return new Matrix($app->make(Calculator::class));
        });

        $this->app->singleton(Graphs::class, function ($app) {
            return new Graphs(
                $app->make(Calculator::class),
                $app->make(DataTable::class),
            );
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
            Calculator::class,
            Dashboard::class,
            DataTable::class,
            Matrix::class,
            Graphs::class,
        ];
    }
}
