<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Event::class)->constrained()->cascadeOnDelete();
            $table->foreignId('boss_id')->nullable()->constrained('bosses')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('left_type')->nullable();
            $table->string('left_value');
            $table->string('right_type')->nullable();
            $table->string('right_value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_assignment_groups');
        Schema::dropIfExists('event_assignments');
    }
};
