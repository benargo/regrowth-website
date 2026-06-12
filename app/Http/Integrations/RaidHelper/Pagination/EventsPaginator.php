<?php

namespace App\Http\Integrations\RaidHelper\Pagination;

use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\PagedPaginator;

class EventsPaginator extends PagedPaginator
{
    protected function isLastPage(Response $response): bool
    {
        return (int) $response->json('eventsTransmitted', 0) < 1000;
    }

    /**
     * @return array<int, mixed>
     */
    protected function getPageItems(Response $response, Request $request): array
    {
        return $response->json('postedEvents', []);
    }

    protected function applyPagination(Request $request): Request
    {
        $request->headers()->add('Page', $this->currentPage);

        return $request;
    }
}
