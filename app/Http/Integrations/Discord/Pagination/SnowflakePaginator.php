<?php

namespace App\Http\Integrations\Discord\Pagination;

use App\Http\Integrations\Discord\Contracts\PaginatesBySnowflake;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Paginator;

/**
 * Pages through a Discord list endpoint by snowflake cursor.
 *
 * For `after`, the cursor is the highest snowflake on the previous page; for `before`, the lowest.
 * The snowflake is read from each item at the request's cursor key. A page with fewer items than
 * the request's `limit` is the last page.
 */
final class SnowflakePaginator extends Paginator
{
    public function __construct(Connector $connector, Request&PaginatesBySnowflake $request)
    {
        parent::__construct($connector, $request);
    }

    protected function applyPagination(Request $request): Request
    {
        if ($this->currentResponse instanceof Response) {
            $request->query()->add($this->cursorRequest()->cursorDirection(), $this->nextCursor($this->currentResponse));
        }

        return $request;
    }

    protected function isLastPage(Response $response): bool
    {
        return count($response->json()) < (int) $this->getOriginalRequest()->query()->get('limit');
    }

    /**
     * @return array<int, mixed>
     */
    protected function getPageItems(Response $response, Request $request): array
    {
        return collect($request->createDtoFromResponse($response))->all();
    }

    /**
     * The numerically highest (`after`) or lowest (`before`) snowflake on the page, as a string.
     */
    private function nextCursor(Response $response): string
    {
        $request = $this->cursorRequest();

        $snowflakes = collect($response->json())
            ->map(fn (array $item): int => (int) data_get($item, $request->cursorKey(), 0));

        return (string) ($request->cursorDirection() === 'before' ? $snowflakes->min() : $snowflakes->max());
    }

    private function cursorRequest(): Request&PaginatesBySnowflake
    {
        /** @var Request&PaginatesBySnowflake */
        return $this->getOriginalRequest();
    }
}
