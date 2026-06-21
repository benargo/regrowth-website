<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class MigrateMediaModelTypesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_updates_stale_loot_council_priority_model_types(): void
    {
        DB::table('media')->insert([
            [
                'model_type' => 'App\\Models\\LootCouncil\\Priority',
                'model_id' => 1,
                'uuid' => '00000000-0000-0000-0000-000000000001',
                'collection_name' => 'blizzard_icons',
                'name' => 'test-icon',
                'file_name' => 'test-icon.jpg',
                'mime_type' => 'image/jpeg',
                'disk' => 'public',
                'conversions_disk' => null,
                'size' => 1024,
                'manipulations' => '[]',
                'custom_properties' => '[]',
                'generated_conversions' => '[]',
                'responsive_images' => '[]',
                'order_column' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'model_type' => 'App\\Models\\Item',
                'model_id' => 99,
                'uuid' => '00000000-0000-0000-0000-000000000002',
                'collection_name' => 'images',
                'name' => 'item-image',
                'file_name' => 'item-image.jpg',
                'mime_type' => 'image/jpeg',
                'disk' => 'public',
                'conversions_disk' => null,
                'size' => 2048,
                'manipulations' => '[]',
                'custom_properties' => '[]',
                'generated_conversions' => '[]',
                'responsive_images' => '[]',
                'order_column' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->artisan('app:migrate-media-model-types')
            ->expectsOutput('Migrating media model types...')
            ->expectsOutput('Updated 1 row(s).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('media', [
            'uuid' => '00000000-0000-0000-0000-000000000001',
            'model_type' => 'App\\Models\\LootPriority',
        ]);

        $this->assertDatabaseHas('media', [
            'uuid' => '00000000-0000-0000-0000-000000000002',
            'model_type' => 'App\\Models\\Item',
        ]);
    }

    #[Test]
    public function it_is_idempotent_when_run_twice(): void
    {
        DB::table('media')->insert([
            'model_type' => 'App\\Models\\LootCouncil\\Priority',
            'model_id' => 1,
            'uuid' => '00000000-0000-0000-0000-000000000003',
            'collection_name' => 'blizzard_icons',
            'name' => 'test-icon',
            'file_name' => 'test-icon.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => null,
            'size' => 1024,
            'manipulations' => '[]',
            'custom_properties' => '[]',
            'generated_conversions' => '[]',
            'responsive_images' => '[]',
            'order_column' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('app:migrate-media-model-types')->assertExitCode(0);

        $this->artisan('app:migrate-media-model-types')
            ->expectsOutput('Updated 0 row(s).')
            ->assertExitCode(0);
    }
}
