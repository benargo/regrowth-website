<?php

use App\Enums\DailyQuestType;
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
        // MySQL requires separate Schema::table calls to drop and re-add an enum column.
        Schema::table('daily_quests', function (Blueprint $table) {
            $table->dropColumn(['rewards', 'type', 'mode']);
        });

        Schema::table('daily_quests', function (Blueprint $table) {
            $table->enum('type', DailyQuestType::cases())->after('name');
        });

        Schema::create('pivot_dailyquest_rewards', function (Blueprint $table) {
            $table->foreignId('daily_quest_id')->constrained('daily_quests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->primary(['daily_quest_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pivot_dailyquest_rewards');

        Schema::table('daily_quests', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('daily_quests', function (Blueprint $table) {
            $table->enum('type', ['Cooking', 'Dungeon', 'Fishing', 'Heroic', 'PvP'])->after('name');
            $table->string('mode')->nullable()->after('instance');
            $table->json('rewards')->nullable()->after('mode');
        });
    }
};
