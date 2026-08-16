<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * Base class for feature tests that assert on results of a MariaDB FULLTEXT
 * search (see Item::matchingName()). InnoDB only synchronizes FULLTEXT index
 * structures at transaction COMMIT, so rows inserted inside RefreshDatabase's
 * per-test transaction are invisible to MATCH() AGAINST() within that same
 * transaction.
 *
 * DatabaseTruncation never wraps tests in a transaction, so fixtures created
 * here are committed the moment they're inserted, and the FULLTEXT index sees
 * them immediately.
 *
 * DatabaseTruncation only truncates in setUp() (before a test runs), not
 * after — see Illuminate\Foundation\Testing\Concerns\InteractsWithTestCaseLifecycle::setUpTraits().
 * That leaves this test's own committed rows sitting in the tables until the
 * *next* test's setUp() truncates them, rather than being cleaned up
 * immediately after this test finishes. Laravel's trait-lifecycle wiring
 * (same method, a few lines below) auto-detects and calls a
 * tearDown{TraitName}() method if one exists via beforeApplicationDestroyed(),
 * so tearDownDatabaseTruncation() below hooks into that convention to force
 * cleanup immediately after each test, not deferred to the start of the next
 * one. Laravel disables FK checks around truncation automatically either way.
 */
abstract class FullTextTestCase extends TestCase
{
    use DatabaseTruncation;

    /**
     * @var array<int, string>
     */
    protected array $tablesToTruncate = ['pivot_items_raids', 'items', 'bosses', 'raids', 'phases'];

    /**
     * @param  callable(): mixed  $create
     * @param  callable(mixed): void  $assert
     */
    protected function withCommittedTransaction(callable $create, callable $assert): void
    {
        $assert($create());
    }

    /**
     * Auto-invoked by Laravel's trait-lifecycle wiring after each test (see
     * InteractsWithTestCaseLifecycle::setUpTraits(), which registers this via
     * beforeApplicationDestroyed() whenever a tearDown{TraitName}() method
     * exists for a trait in use). Truncates immediately after this test's own
     * committed rows are no longer needed, instead of leaving them in place
     * until the next test's setUp() truncates them.
     */
    protected function tearDownDatabaseTruncation(): void
    {
        $this->truncateDatabaseTables();
    }
}
