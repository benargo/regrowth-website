<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the colour and grants_permissions columns, flagging roles that already have permissions.
     */
    public function up(): void
    {
        Schema::table('discord_roles', function (Blueprint $table) {
            $table->binary('primary_color', length: 3)->nullable()->after('position');
            $table->binary('secondary_color', length: 3)->nullable()->after('primary_color');
            $table->binary('tertiary_color', length: 3)->nullable()->after('secondary_color');
            $table->boolean('grants_permissions')->default(false)->after('is_visible');
        });

        DB::table('discord_roles')
            ->whereIn('id', DB::table('discord_role_has_permissions')->select('discord_role_id'))
            ->update(['grants_permissions' => true]);
    }
};
