<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pruned_models', function (Blueprint $table) {
            $table->uuid('id');
            $table->string('type');
            $table->timestamp('pruned_at')->useCurrent();
            $table->primary(['id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pruned_models');
    }
};
