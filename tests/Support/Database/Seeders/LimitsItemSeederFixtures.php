<?php

namespace Tests\Support\Database\Seeders;

use Database\Seeders\ItemSeeder;
use Illuminate\Database\Eloquent\Model;

trait LimitsItemSeederFixtures
{
    /**
     * Truncate ItemSeeder::$items to only the given item ids, for every
     * instance the container resolves from this point on — including ones
     * built indirectly, e.g. by an Artisan command calling
     * $this->laravel->make(ItemSeeder::class).
     *
     * @param  array<int, int>  $itemIds
     */
    protected function limitItemSeederTo(array $itemIds): void
    {
        $this->app->extend(ItemSeeder::class, function (ItemSeeder $seeder) use ($itemIds): ItemSeeder {
            $reflection = new \ReflectionProperty(ItemSeeder::class, 'items');
            $allItems = $reflection->getValue($seeder);

            $reflection->setValue($seeder, array_values(array_filter(
                $allItems,
                fn (array $item): bool => in_array($item['id'], $itemIds, true),
            )));

            return $seeder;
        });
    }

    /**
     * Run the item seeder directly against only the given item ids, so a
     * test does not process every hardcoded row.
     *
     * @param  array<int, int>  $itemIds
     */
    protected function seedSpecificItems(array $itemIds): ItemSeeder
    {
        $this->limitItemSeederTo($itemIds);

        $seeder = app(ItemSeeder::class);

        Model::unguarded(fn () => $seeder->run());

        return $seeder;
    }
}
