<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

return new class extends Migration
{
    /**
     * Add the nullable surname column and the stored surname_key column
     * (COALESCE(surname, '')), split existing surname-version names into name and
     * surname, and replace the (game_version_id, name) unique index with
     * (game_version_id, name, surname_key).
     */
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('surname')->nullable()->after('name');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE characters MODIFY surname VARCHAR(255) NULL COLLATE utf8mb4_bin');
        }

        Schema::table('characters', function (Blueprint $table) {
            $surnameKey = $table->string('surname_key')->storedAs("COALESCE(surname, '')")->after('surname');

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $surnameKey->collation('utf8mb4_bin');
            }
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->index('game_version_id');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropUnique(['game_version_id', 'name']);
        });

        Artisan::call('app:split-character-surnames', [], $this->output());

        Schema::table('characters', function (Blueprint $table) {
            $table->unique(['game_version_id', 'name', 'surname_key']);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropIndex(['game_version_id']);
        });
    }

    /**
     * Join each surname back into the name, restore the (game_version_id, name)
     * unique index and drop the surname_key and surname columns.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->index('game_version_id');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropUnique(['game_version_id', 'name', 'surname_key']);
        });

        Artisan::call('app:split-character-surnames', ['--revert' => true], $this->output());

        Schema::table('characters', function (Blueprint $table) {
            $table->unique(['game_version_id', 'name']);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropIndex(['game_version_id']);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('surname_key');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('surname');
        });
    }

    /**
     * Output the command writes to: nothing while running tests, the console otherwise.
     */
    private function output(): OutputInterface
    {
        return app()->runningUnitTests() ? new NullOutput : new ConsoleOutput;
    }
};
