<?php

namespace App\Services\WarcraftLogs;

use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report as ReportModel;
use App\Services\WarcraftLogs\Data\Report;
use App\Services\WarcraftLogs\Traits\Paginates;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class Reports extends BaseService
{
    use Paginates;

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
            return $this->paginateAll(
                fn (int $page) => $this->fetchReportsPage($page, null),
            )->sortByDesc(fn (Report $r) => $r->startTime)->values();
        }

        return $this->paginateAllAcrossTags(
            $this->guildTagIDs,
            fn (int $tagID) => fn (int $page) => $this->fetchReportsPage($page, $tagID),
        )->sortByDesc(fn (Report $r) => $r->startTime)->values();
    }

    /**
     * Lazily fetch all reports across configured guild tags (or by guild ID if none set).
     * Results are deduplicated by report code. Items are yielded as they are fetched.
     *
     * @return LazyCollection<int, Report>
     */
    public function lazy(): LazyCollection
    {
        if (empty($this->guildTagIDs)) {
            return $this->paginateLazy(
                fn (int $page) => $this->fetchReportsPage($page, null),
            );
        }

        return $this->paginateLazyAcrossTags(
            $this->guildTagIDs,
            fn (int $tagID) => $this->paginateLazy(
                fn (int $page) => $this->fetchReportsPage($page, $tagID),
            ),
        );
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
     * Fetch a single page of reports and return a normalized result.
     *
     * @return array{items: array<Report>, hasMorePages: bool}
     */
    protected function fetchReportsPage(int $page, ?int $guildTagID): array
    {
        $variables = $guildTagID !== null
            ? ['guildTagID' => $guildTagID]
            : ['guildID' => $this->guildId];

        $variables['page'] = $page;
        $variables['limit'] = 100;

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

        $items = array_map(
            fn (array $reportData) => Report::fromArray($reportData),
            $pageData,
        );

        return [
            'items' => $items,
            'hasMorePages' => $reportsData['has_more_pages'] ?? false,
        ];
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
                        guildTag {
                            id
                            name
                        }
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
