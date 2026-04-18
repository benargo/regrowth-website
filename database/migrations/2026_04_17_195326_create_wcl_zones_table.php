<?php

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
        Schema::create('wcl_zones', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->json('difficulties');
            $table->json('expansion');
            $table->boolean('is_frozen')->default(false);
            $table->timestamps();
        });

        DB::table('raid_reports')
            ->whereNotNull('zone_id')
            ->whereNotIn('zone_id', DB::table('wcl_zones')->select('id'))
            ->update(['zone_id' => null]);

        Schema::table('raid_reports', function (Blueprint $table) {
            $table->foreign('zone_id')->references('id')->on('wcl_zones')->nullOnDelete();
            $table->dropColumn('zone_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raid_reports', function (Blueprint $table) {
            $table->dropForeign(['zone_id']);
            $table->string('zone_name')->nullable();
        });

        Schema::dropIfExists('wcl_zones');
    }
};
