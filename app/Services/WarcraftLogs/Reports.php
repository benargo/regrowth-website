<?php

namespace App\Services\WarcraftLogs;

use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report as ReportModel;
use App\Services\WarcraftLogs\Data\Report;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class Reports extends BaseService
{
    protected int $cacheTtl = 300; // 5 minutes

    /**
     * The guild tag IDs to query reports for.
     *
     * @var array<int>
     */
    protected array $guildTagIDs = [];

    /**
     * Optional start time filter in milliseconds.
     */
    protected ?float $startTime = null;

    /**
     * Optional end time filter in milliseconds.
     */
    protected ?float $endTime = null;

    /**
     * Set the guild tags to filter reports by.
     *
     * @param  Collection<int, GuildTag>  $guildTags
     */
    public function byGuildTags(Collection $guildTags): static
    {
        $this->guildTagIDs = $guildTags->pluck('id')->all();

        return $this;
    }

    /**
     * Set the start time filter.
     */
    public function startTime(?Carbon $startTime): static
    {
        $this->startTime = $startTime?->valueOf();

        return $this;
    }

    /**
     * Set the end time filter.
     */
    public function endTime(?Carbon $endTime): static
    {
        $this->endTime = $endTime?->valueOf();

        return $this;
    }

    /**
     * Fetch all reports across configured guild tags (or by guild ID if none set).
     * Results are deduplicated by report code and sorted by start time descending.
     *
     * @return Collection<int, Report>
     */
    public function get(): Collection
    {
        if (empty($this->guildTagIDs)) {
            return $this->fetchAllPages([
                'guildID' => $this->guildId,
            ])->sortByDesc(fn (Report $r) => $r->startTime)->values();
        }

        $allReports = collect();

        foreach ($this->guildTagIDs as $tagID) {
            $tagReports = $this->fetchAllPages([
                'guildTagID' => $tagID,
            ]);

            foreach ($tagReports as $report) {
                $allReports[$report->code] = $report;
            }
        }

        return $allReports
            ->sortByDesc(fn (Report $r) => $r->startTime)
            ->values();
    }

    /**
     * Fetch all reports and persist them to the database via updateOrCreate.
     * Returns the same collection of Data\Report objects as get().
     *
     * @return Collection<int, Report>
     */
    public function toDatabase(): Collection
    {
        $reports = $this->get();

        $reports->each(function (Report $report) {
            ReportModel::updateOrCreate(
                ['code' => $report->code],
                [
                    'title' => $report->title,
                    'start_time' => $report->startTime,
                    'end_time' => $report->endTime,
                    'zone_id' => $report->zone?->id,
                    'zone_name' => $report->zone?->name,
                ],
            );
        });

        return $reports;
    }

    /**
     * Fetch all pages of reports for the given variables.
     *
     * @param  array<string, mixed>  $baseVariables
     * @return Collection<int, Report>
     */
    protected function fetchAllPages(array $baseVariables): Collection
    {
        $reports = collect();
        $page = 1;
        $guildTagID = $baseVariables['guildTagID'] ?? null;

        do {
            $variables = array_merge($baseVariables, [
                'page' => $page,
                'limit' => 100,
            ]);

            if ($this->startTime !== null) {
                $variables['startTime'] = $this->startTime;
            }

            if ($this->endTime !== null) {
                $variables['endTime'] = $this->endTime;
            }

            $data = $this->query(
                $this->buildGraphQuery($guildTagID),
                $variables,
                $this->cacheTtl,
            );

            $reportsData = $data['reportData']['reports'] ?? [];
            $pageData = $reportsData['data'] ?? [];

            foreach ($pageData as $reportData) {
                $report = Report::fromArray($reportData);
                $reports[$report->code] = $report;
            }

            $hasMorePages = $reportsData['has_more_pages'] ?? false;
            $page++;
        } while ($hasMorePages);

        return $reports;
    }

    /**
     * Build the GraphQL query for fetching reports.
     */
    protected function buildGraphQuery(?int $guildTagID = null): string
    {
        $variableDefinitions = ['$page: Int', '$limit: Int'];
        $reportsArgs = ['page: $page', 'limit: $limit'];

        if ($guildTagID !== null) {
            array_unshift($variableDefinitions, '$guildTagID: Int!');
            array_unshift($reportsArgs, 'guildTagID: $guildTagID');
        } else {
            array_unshift($variableDefinitions, '$guildID: Int!');
            array_unshift($reportsArgs, 'guildID: $guildID');
        }

        if ($this->startTime !== null) {
            $variableDefinitions[] = '$startTime: Float';
            $reportsArgs[] = 'startTime: $startTime';
        }

        if ($this->endTime !== null) {
            $variableDefinitions[] = '$endTime: Float';
            $reportsArgs[] = 'endTime: $endTime';
        }

        $variableDefinitionsStr = implode(', ', $variableDefinitions);
        $reportsArgsStr = implode(', ', $reportsArgs);

        return <<<GRAPHQL
        query GetReports({$variableDefinitionsStr}) {
            reportData {
                reports({$reportsArgsStr}) {
                    data {
                        code
                        title
                        startTime
                        endTime
                        zone {
                            id
                            name
                        }
                    }
                    current_page
                    has_more_pages
                }
            }
        }
        GRAPHQL;
    }
}
