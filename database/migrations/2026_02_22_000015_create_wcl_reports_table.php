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
        Schema::create('wcl_reports', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('title');
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->foreignId('guild_tag_id')->nullable()->constrained('wcl_guild_tags')->nullOnDelete();
            $table->unsignedInteger('zone_id')->nullable();
            $table->string('zone_name')->nullable();
            $table->timestamps();
        });

        Schema::create('pivot_characters_wcl_reports', function (Blueprint $table) {
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('wcl_report_code');
            $table->foreign('wcl_report_code')->references('code')->on('wcl_reports')->cascadeOnDelete();
            $table->unsignedTinyInteger('presence')->default(0);
            $table->primary(['character_id', 'wcl_report_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pivot_characters_wcl_reports');
        Schema::dropIfExists('wcl_reports');
    }
};
