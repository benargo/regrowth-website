<?php

namespace App\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Enums\Endpoints;
use App\Services\WarcraftLogs\ValueObjects\Expansion;
use App\Services\WarcraftLogs\ValueObjects\Zone;
use InvalidArgumentException;

class WorldData extends BaseService
{
    /**
     * The endpoint to use for world data queries.
     *
     * This is set to "fresh" to ensure we get the most up-to-date information, as expansions and zones can change over time.
     */
    protected Endpoints $endpoint = Endpoints::FRESH;

    /**
     * @var array<Expansion>
     */
    private array $expansions = [];

    /**
     * Zones fetched, keyed by expansion ID (or "all" when no filter was applied).
     *
     * @var array<string, array<Zone>>
     */
    private array $zones = [];

    private const GET_EXPANSIONS_QUERY = <<<'GRAPHQL'
        query GetExpansions {
          worldData {
            expansions {
              id
              name
              zones {
                id
                name
              }
            }
          }
        }
        GRAPHQL;

    private const GET_ZONES_QUERY = <<<'GRAPHQL'
        query GetZonesForExpansion($expansionId: Int) {
          worldData {
            zones(expansion_id: $expansionId) {
              id
              name
              frozen
            }
          }
        }
        GRAPHQL;

    /**
     * Fetch all expansions, including their zones.
     *
     * Results are cached in-memory for the lifetime of the service instance.
     *
     * @return array<Expansion>
     */
    public function getExpansions(): array
    {
        if (! empty($this->expansions)) {
            return $this->expansions;
        }

        $data = $this->query(self::GET_EXPANSIONS_QUERY);

        $this->expansions = array_map(
            fn (array $expansion) => Expansion::fromArray($expansion),
            $data['worldData']['expansions'] ?? [],
        );

        return $this->expansions;
    }

    /**
     * Fetch zones, optionally scoped to a specific expansion.
     *
     * Results are cached in-memory per expansion ID for the lifetime of the service instance.
     *
     * @return array<Zone>
     *
     * @throws InvalidArgumentException When the given expansion ID does not exist.
     */
    public function getZones(?int $expansionId = null): array
    {
        $memoryKey = $expansionId !== null ? (string) $expansionId : 'all';

        if (isset($this->zones[$memoryKey])) {
            return $this->zones[$memoryKey];
        }

        if ($expansionId !== null) {
            $validIds = array_map(fn (Expansion $e) => $e->id, $this->getExpansions());

            if (! in_array($expansionId, $validIds, strict: true)) {
                throw new InvalidArgumentException("Expansion ID {$expansionId} is not valid.");
            }
        }

        $variables = $expansionId !== null ? ['expansionId' => $expansionId] : [];

        $data = $this->query(self::GET_ZONES_QUERY, $variables);

        $this->zones[$memoryKey] = array_map(
            fn (array $zone) => Zone::fromArray($zone),
            $data['worldData']['zones'] ?? [],
        );

        return $this->zones[$memoryKey];
    }
}
