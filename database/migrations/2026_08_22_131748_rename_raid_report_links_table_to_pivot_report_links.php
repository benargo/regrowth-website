<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('raid_report_links', 'pivot_report_links');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('pivot_report_links', 'raid_report_links');
    }
};
