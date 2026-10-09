<?php

namespace App\Http\Integrations\WarcraftLogs\Requests;

use App\Http\Integrations\WarcraftLogs\Data\Reports\ReportData;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use Carbon\CarbonInterface;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

final class GetReportsRequest extends WarcraftLogsRequest implements Paginatable
{
    public function __construct(
        protected readonly int $guildTagId,
        WarcraftLogsNamespace $namespace,
        protected readonly ?CarbonInterface $startTime = null,
        protected readonly ?CarbonInterface $endTime = null,
    ) {
        parent::__construct($namespace);
    }

    /**
     * The time-window variables are declared only when set, matching the
     * legacy query WCL has always received.
     */
    protected function graphQLQuery(): string
    {
        $definitions = ['$guildTagID: Int!', '$page: Int', '$limit: Int'];
        $arguments = ['guildTagID: $guildTagID', 'page: $page', 'limit: $limit'];

        if ($this->startTime !== null) {
            $definitions[] = '$startTime: Float';
            $arguments[] = 'startTime: $startTime';
        }

        if ($this->endTime !== null) {
            $definitions[] = '$endTime: Float';
            $arguments[] = 'endTime: $endTime';
        }

        $definitionList = implode(', ', $definitions);
        $argumentList = implode(', ', $arguments);

        return <<<GRAPHQL
        query GetReports({$definitionList}) {
            reportData {
                reports({$argumentList}) {
                    data {
                        code
                        title
                        startTime
                        endTime
                        guildTag {
                            id
                            name
                        }
                        zone {
                            id
                            name
                            difficulties {
                                id
                                name
                                sizes
                            }
                            expansion {
                                id
                                name
                            }
                        }
                    }
                    current_page
                    has_more_pages
                }
            }
        }
        GRAPHQL;
    }

    /**
     * @return array<string, int|float>
     */
    protected function variables(): array
    {
        $variables = [
            'guildTagID' => $this->guildTagId,
            'page' => 1,
            'limit' => 100,
        ];

        if ($this->startTime !== null) {
            $variables['startTime'] = (float) $this->startTime->getTimestampMs();
        }

        if ($this->endTime !== null) {
            $variables['endTime'] = (float) $this->endTime->getTimestampMs();
        }

        return $variables;
    }

    public function cacheExpiryInSeconds(): int
    {
        return 300; // 5 minutes
    }

    /**
     * @return array<int, ReportData>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return ReportData::collect($response->json('data.reportData.reports.data') ?? []);
    }
}
