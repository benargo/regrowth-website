<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $table->dropForeign(['item_id']);
            } else {
                $itemForeignKey = collect(Schema::getForeignKeys('comments'))
                    ->firstWhere('columns', ['item_id'])['name'];
                $table->dropForeign($itemForeignKey);
            }
            $table->string('commentable_id')->nullable()->after('id');
            $table->string('commentable_type')->nullable()->after('commentable_id');
            $table->index(['commentable_type', 'commentable_id']);
        });

        DB::table('comments')->orderBy('id')->chunkById(200, function ($comments) {
            foreach ($comments as $comment) {
                DB::table('comments')
                    ->where('id', $comment->id)
                    ->update([
                        'commentable_id' => (string) $comment->item_id,
                        'commentable_type' => 'App\\Models\\Item',
                    ]);
            }
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->string('commentable_id')->change();
            $table->string('commentable_type')->change();
            $table->dropColumn('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable()->after('id');
        });

        DB::table('comments')
            ->where('commentable_type', 'App\\Models\\Item')
            ->orderBy('id')
            ->chunkById(200, function ($comments) {
                foreach ($comments as $comment) {
                    DB::table('comments')
                        ->where('id', $comment->id)
                        ->update(['item_id' => (int) $comment->commentable_id]);
                }
            });

        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->notNull()->change();
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->dropIndex(['commentable_type', 'commentable_id']);
            $table->dropColumn(['commentable_id', 'commentable_type']);
        });
    }
};
