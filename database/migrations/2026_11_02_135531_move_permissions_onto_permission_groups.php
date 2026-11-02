<?php

use App\Models\PermissionGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
            $table->string('description', 160)->nullable()->after('label');
            $table->foreignIdFor(PermissionGroup::class)->nullable()->after('description')->constrained()->nullOnDelete();
        });

        $nameOverrides = ['warcraftlogs' => 'Warcraft Logs'];

        $slugsByName = DB::table('permissions')
            ->whereNotNull('group')
            ->distinct()
            ->pluck('group')
            ->mapWithKeys(fn (string $slug) => [$slug => $nameOverrides[$slug] ?? Str::headline($slug)])
            ->sort();

        $order = 1;

        foreach ($slugsByName as $slug => $name) {
            $id = (string) Str::uuid7();

            DB::table('permission_groups')->insert([
                'id' => $id,
                'name' => $name,
                'sort_order' => $order++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('permissions')->where('group', $slug)->update(['permission_group_id' => $id]);
        }

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('group')->nullable();
        });

        $slugOverrides = ['Warcraft Logs' => 'warcraftlogs'];

        DB::table('permission_groups')->get(['id', 'name'])->each(
            fn (object $group) => DB::table('permissions')
                ->where('permission_group_id', $group->id)
                ->update(['group' => $slugOverrides[$group->name] ?? Str::slug($group->name)])
        );

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(PermissionGroup::class);
            $table->dropColumn(['label', 'description']);
        });
    }
};
