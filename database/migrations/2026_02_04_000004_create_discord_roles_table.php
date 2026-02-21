<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discord_roles', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('position')->unique();
            $table->boolean('can_comment_on_loot_items')->default(false);
            $table->timestamps();
        });

        Schema::create('discord_role_user', function (Blueprint $table) {
            $table->string('discord_role_id');
            $table->string('user_id');
            $table->primary(['discord_role_id', 'user_id']);
            $table->foreign('discord_role_id')->references('id')->on('discord_roles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        $now = Carbon::now();

        DB::table('discord_roles')->insert([
            ['id' => '829021769448816691', 'name' => 'Officer', 'position' => 5, 'can_comment_on_loot_items' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1467994755953852590', 'name' => 'Loot Councillor', 'position' => 4, 'can_comment_on_loot_items' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '1265247017215594496', 'name' => 'Raider', 'position' => 3, 'can_comment_on_loot_items' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '829022020301094922', 'name' => 'Member', 'position' => 2, 'can_comment_on_loot_items' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '829022292590985226', 'name' => 'Guest', 'position' => 1, 'can_comment_on_loot_items' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $knownRoleIds = DB::table('discord_roles')->pluck('id')->toArray();

        DB::table('users')->whereNotNull('roles')->get()->each(function ($user) use ($knownRoleIds) {
            $roles = json_decode($user->roles, true) ?? [];

            foreach ($roles as $roleId) {
                if (in_array($roleId, $knownRoleIds)) {
                    DB::table('discord_role_user')->insertOrIgnore([
                        'discord_role_id' => $roleId,
                        'user_id' => $user->id,
                    ]);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->nullable();
        });

        DB::table('users')->get()->each(function ($user) {
            $roleIds = DB::table('discord_role_user')
                ->where('user_id', $user->id)
                ->pluck('discord_role_id')
                ->toArray();

            DB::table('users')->where('id', $user->id)->update([
                'roles' => json_encode($roleIds),
            ]);
        });

        Schema::dropIfExists('discord_role_user');
        Schema::dropIfExists('discord_roles');
    }
};
