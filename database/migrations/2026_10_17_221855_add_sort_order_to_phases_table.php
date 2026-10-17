<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the order of a game version's phases, numbering each version's
     * phases 1…n by phase number.
     */
    public function up(): void
    {
        Schema::table('phases', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('start_date');
        });

        $phasesByGameVersion = DB::table('phases')
            ->orderBy('number')
            ->orderBy('id')
            ->get(['id', 'game_version_id'])
            ->groupBy('game_version_id');

        foreach ($phasesByGameVersion as $phases) {
            foreach ($phases->values() as $index => $phase) {
                DB::table('phases')->where('id', $phase->id)->update(['sort_order' => $index + 1]);
            }
        }
    }
};
