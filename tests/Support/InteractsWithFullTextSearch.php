<?php

namespace Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * InnoDB (MySQL/MariaDB) only synchronizes FULLTEXT index structures at
 * transaction COMMIT, so rows inserted inside RefreshDatabase's per-test
 * transaction are invisible to MATCH() AGAINST() within that same
 * transaction. $create's writes must be committed before $assert queries
 * them, which is why assertions run in a separate callback rather than
 * inline after the factory calls.
 *
 * Because the commit is real SQL (not a savepoint), fixtures created this
 * way are permanently persisted and would leak into later tests unless the
 * trait cleans up after itself. Callers must declare which tables are in
 * scope via usingModel() before calling withCommittedTransaction(); cleanup
 * deletes all rows from exactly those tables, in reverse of the declared
 * order, so it never depends on the create closure remembering to return
 * every model it created.
 *
 * DELETE is used over TRUNCATE because MariaDB refuses to TRUNCATE a table
 * referenced by any foreign key, even an empty one — several tables outside
 * this trait's scope (e.g. pivot_items_priorities) reference items/bosses/
 * raids/phases, so TRUNCATE fails structurally regardless of row counts.
 *
 * On drivers without this behaviour (e.g. SQLite, where whereFullText()
 * falls back to a plain LIKE clause), the commit/reopen dance is
 * unnecessary, so it's skipped entirely and $create/$assert just run in
 * the outer per-test transaction as normal.
 */
trait InteractsWithFullTextSearch
{
    /** @var list<class-string<Model>> */
    private array $fullTextModels = [];

    /**
     * @param  class-string<Model>  ...$models  Parent-to-child order (e.g. Phase::class, Raid::class, Boss::class, Item::class).
     */
    private function usingModel(string ...$models): self
    {
        $this->fullTextModels = $models;

        return $this;
    }

    /**
     * @param  callable(): array<string, Model>  $create
     * @param  callable(array<string, Model>): void  $assert
     */
    private function withCommittedTransaction(callable $create, callable $assert): void
    {
        if ($this->fullTextModels === []) {
            throw new LogicException('Call usingModel() before withCommittedTransaction() so cleanup knows which tables to clean up.');
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $assert($create());
            $this->fullTextModels = [];

            return;
        }

        DB::connection()->commit();
        DB::connection()->beginTransaction();

        try {
            $models = $create();
            DB::connection()->commit();
            DB::connection()->beginTransaction();

            $assert($models);
        } finally {
            foreach (array_reverse($this->fullTextModels) as $model) {
                DB::connection()->statement('DELETE FROM '.(new $model)->getTable());
            }
            DB::connection()->commit();
            DB::connection()->beginTransaction();

            $this->fullTextModels = [];
        }
    }
}
