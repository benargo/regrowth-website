<?php

use App\Enums\ItemQuality;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('lootcouncil_items')) {
            return;
        }

        Schema::table('lootcouncil_items', function (Blueprint $table) {
            $table->dropColumn('icon');
        });

        Schema::table('lootcouncil_items', function (Blueprint $table) {
            $table->unsignedBigInteger('raid_id')->nullable()->change();
            $table->enum('quality', ItemQuality::cases())->after('name');
        });

        Schema::rename('lootcouncil_items', 'items');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('items')) {
            return;
        }

        Schema::rename('items', 'lootcouncil_items');

        Schema::table('lootcouncil_items', function (Blueprint $table) {
            $table->dropColumn('quality');
        });

        Schema::table('lootcouncil_items', function (Blueprint $table) {
            $table->json('icon')->nullable()->after('name');
        });
    }
};
