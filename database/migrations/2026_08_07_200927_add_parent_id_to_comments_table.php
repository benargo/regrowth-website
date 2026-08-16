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
        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');

            // No cascade or null-on-delete: soft-deleting a root must leave its
            // replies in place so the root can render as a tombstone.
            $table->foreign('parent_id')->references('id')->on('comments');

            $table->index('parent_id');
            $table->index(['parent_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id', 'created_at']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
