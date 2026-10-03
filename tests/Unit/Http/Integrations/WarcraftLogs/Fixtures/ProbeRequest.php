<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Fixtures;

use App\Http\Integrations\WarcraftLogs\Requests\WarcraftLogsRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;

/**
 * Minimal concrete request used only to exercise connector-level behaviour.
 */
final class ProbeRequest extends WarcraftLogsRequest
{
    /**
     * @param  array<string, mixed>  $probeVariables
     */
    public function __construct(
        WarcraftLogsNamespace $namespace,
        private readonly array $probeVariables = [],
    ) {
        parent::__construct($namespace);
    }

    public function cacheExpiryInSeconds(): int
    {
        return 300;
    }

    protected function graphQLQuery(): string
    {
        return 'query Probe($page: Int) { probe(page: $page) }';
    }

    /**
     * @return array<string, mixed>
     */
    protected function variables(): array
    {
        return $this->probeVariables;
    }
}
