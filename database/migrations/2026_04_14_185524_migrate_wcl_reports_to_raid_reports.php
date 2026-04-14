<?php

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
        // 1. Create the new raid_reports table with UUID primary key
        Schema::create('raid_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->nullable()->unique();
            $table->string('title');
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->foreignId('guild_tag_id')->nullable()->constrained('wcl_guild_tags')->nullOnDelete();
            $table->unsignedInteger('zone_id')->nullable();
            $table->string('zone_name')->nullable();
            $table->timestamps();
        });

        // 2. Copy data from wcl_reports using PHP chunks for cross-database UUID generation
        DB::table('wcl_reports')->orderBy('code')->chunk(500, function ($reports) {
            DB::table('raid_reports')->insert(
                $reports->map(fn ($r) => [
                    'id' => (string) Str::uuid(),
                    'code' => $r->code,
                    'title' => $r->title,
                    'start_time' => $r->start_time,
                    'end_time' => $r->end_time,
                    'guild_tag_id' => $r->guild_tag_id,
                    'zone_id' => $r->zone_id,
                    'zone_name' => $r->zone_name,
                    'created_at' => $r->created_at,
                    'updated_at' => $r->updated_at,
                ])->all()
            );
        });

        // 3. Create the new character-report pivot table with UUID FK
        Schema::create('pivot_characters_raid_reports', function (Blueprint $table) {
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->uuid('raid_report_id');
            $table->foreign('raid_report_id')->references('id')->on('raid_reports')->cascadeOnDelete();
            $table->unsignedTinyInteger('presence')->default(0);
            $table->primary(['character_id', 'raid_report_id']);
        });

        // 4. Populate pivot_characters_raid_reports from old pivot (JOIN works on MySQL and SQLite)
        DB::statement('
            INSERT INTO pivot_characters_raid_reports (character_id, raid_report_id, presence)
            SELECT p.character_id, r.id, p.presence
            FROM pivot_characters_wcl_reports p
            JOIN raid_reports r ON r.code = p.wcl_report_code
        ');

        // 5. Create the new report links table with UUID FKs
        Schema::create('raid_report_links', function (Blueprint $table) {
            $table->uuid('report_1');
            $table->uuid('report_2');
            $table->foreign('report_1')->references('id')->on('raid_reports')->cascadeOnDelete();
            $table->foreign('report_2')->references('id')->on('raid_reports')->cascadeOnDelete();
            $table->string('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->primary(['report_1', 'report_2']);
        });

        // 6. Populate raid_report_links from the old links table
        DB::statement('
            INSERT INTO raid_report_links (report_1, report_2, created_by, created_at, updated_at)
            SELECT r1.id, r2.id, p.created_by, p.created_at, p.updated_at
            FROM pivot_wcl_reports_links p
            JOIN raid_reports r1 ON r1.code = p.report_1
            JOIN raid_reports r2 ON r2.code = p.report_2
        ');

        // 7–9. Drop old tables (pivot tables first to satisfy FK constraints)
        Schema::dropIfExists('pivot_wcl_reports_links');
        Schema::dropIfExists('pivot_characters_wcl_reports');
        Schema::dropIfExists('wcl_reports');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Recreate wcl_reports with original structure
        Schema::create('wcl_reports', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('title');
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->foreignId('guild_tag_id')->nullable()->constrained('wcl_guild_tags')->nullOnDelete();
            $table->unsignedInteger('zone_id')->nullable();
            $table->string('zone_name')->nullable();
            $table->timestamps();
        });

        // 2. Copy data back (code is still stored in raid_reports)
        DB::table('raid_reports')->whereNotNull('code')->orderBy('code')->chunk(500, function ($reports) {
            DB::table('wcl_reports')->insert(
                $reports->map(fn ($r) => [
                    'code' => $r->code,
                    'title' => $r->title,
                    'start_time' => $r->start_time,
                    'end_time' => $r->end_time,
                    'guild_tag_id' => $r->guild_tag_id,
                    'zone_id' => $r->zone_id,
                    'zone_name' => $r->zone_name,
                    'created_at' => $r->created_at,
                    'updated_at' => $r->updated_at,
                ])->all()
            );
        });

        // 3. Recreate original character pivot table
        Schema::create('pivot_characters_wcl_reports', function (Blueprint $table) {
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('wcl_report_code');
            $table->foreign('wcl_report_code')->references('code')->on('wcl_reports')->cascadeOnDelete();
            $table->unsignedTinyInteger('presence')->default(0);
            $table->primary(['character_id', 'wcl_report_code']);
        });

        // 4. Populate original character pivot from new pivot
        DB::statement('
            INSERT INTO pivot_characters_wcl_reports (character_id, wcl_report_code, presence)
            SELECT p.character_id, r.code, p.presence
            FROM pivot_characters_raid_reports p
            JOIN raid_reports r ON r.id = p.raid_report_id
            WHERE r.code IS NOT NULL
        ');

        // 5. Recreate original links table
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

        // 6. Populate original links table
        DB::statement('
            INSERT INTO pivot_wcl_reports_links (report_1, report_2, created_by, created_at, updated_at)
            SELECT r1.code, r2.code, l.created_by, l.created_at, l.updated_at
            FROM raid_report_links l
            JOIN raid_reports r1 ON r1.id = l.report_1
            JOIN raid_reports r2 ON r2.id = l.report_2
            WHERE r1.code IS NOT NULL AND r2.code IS NOT NULL
        ');

        // 7. Drop new tables
        Schema::dropIfExists('raid_report_links');
        Schema::dropIfExists('pivot_characters_raid_reports');
        Schema::dropIfExists('raid_reports');
    }
};
