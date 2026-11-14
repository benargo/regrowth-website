<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the per-outcome row counts to GRM uploads.
     */
    public function up(): void
    {
        Schema::table('grm_uploads', function (Blueprint $table) {
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
        });
    }

    /**
     * Drop the per-outcome row counts.
     */
    public function down(): void
    {
        Schema::table('grm_uploads', function (Blueprint $table) {
            $table->dropColumn(['processed_count', 'skipped_count', 'warning_count', 'error_count']);
        });
    }
};
