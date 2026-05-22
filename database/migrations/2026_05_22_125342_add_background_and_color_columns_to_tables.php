<?php

use App\Enums\RaidBackground;
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
        Schema::table('raids', function (Blueprint $table) {
            $table->enum('background_css_class', RaidBackground::cases())->nullable()->after('difficulty');
            $table->binary('color', length: 3)->nullable()->after('background_css_class');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->enum('background_css_class', RaidBackground::cases())->nullable()->after('end_time');
            $table->binary('color', length: 3)->nullable()->after('background_css_class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('color');
            $table->dropColumn('background_css_class');
        });

        Schema::table('raids', function (Blueprint $table) {
            $table->dropColumn('color');
            $table->dropColumn('background_css_class');
        });
    }
};
