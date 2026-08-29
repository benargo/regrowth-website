<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('event_assignment_groups')->update([
            'sort_order' => DB::raw('sort_order + 1'),
        ]);

        DB::table('event_assignments')->whereNotNull('sort_order')->update([
            'sort_order' => DB::raw('sort_order + 1'),
        ]);

        $nullRows = DB::table('event_assignments')
            ->whereNull('sort_order')
            ->orderBy('event_id')
            ->orderBy('boss_id')
            ->orderBy('group_id')
            ->orderBy('id')
            ->get(['id', 'event_id', 'boss_id', 'group_id']);

        $nextOrderByScope = [];

        foreach ($nullRows as $row) {
            $scopeKey = "{$row->event_id}|{$row->boss_id}|{$row->group_id}";
            $nextOrderByScope[$scopeKey] = ($nextOrderByScope[$scopeKey] ?? 0) + 1;

            DB::table('event_assignments')
                ->where('id', $row->id)
                ->update(['sort_order' => $nextOrderByScope[$scopeKey]]);
        }
    }

    public function down(): void
    {
        DB::table('event_assignment_groups')
            ->where('sort_order', '>', 0)
            ->update([
                'sort_order' => DB::raw('sort_order - 1'),
            ]);

        DB::table('event_assignments')
            ->where('sort_order', '>', 0)
            ->update([
                'sort_order' => DB::raw('sort_order - 1'),
            ]);
    }
};
