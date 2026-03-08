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
        Schema::create('pivot_wcl_reports_links', function (Blueprint $table) {
            $table->string('report_1');
            $table->string('report_2');
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->foreign('report_1')->references('code')->on('wcl_reports')->cascadeOnDelete();
            $table->foreign('report_2')->references('code')->on('wcl_reports')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->primary(['report_1', 'report_2']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pivot_wcl_reports_links');
    }
};
