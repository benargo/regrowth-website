<?php

namespace App\Http\Integrations\Blizzard\Pagination;

use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Paginator;

class ItemSearchPaginator extends Paginator
{
    protected ?int $perPageLimit = 50;

    protected function isLastPage(Response $response): bool
    {
        $page = (int) $response->json('page', 1);
        $pageCount = (int) $response->json('pageCount', 1);

        return $page >= $pageCount;
    }

    protected function getPageItems(Response $response, Request $request): array
    {
        return $response->json('results', []);
    }

    protected function applyPagination(Request $request): Request
    {
        $request->query()->add('_page', $this->currentPage);

        if ($this->perPageLimit !== null) {
            $request->query()->add('_pageSize', min($this->perPageLimit, 1000));
        }

        return $request;
    }

    public function getResultCount(): ?int
    {
        return $this->currentResponse?->json('resultCount');
    }

    public function getPageCount(): ?int
    {
        return $this->currentResponse?->json('pageCount');
    }

    public function getMaxPageSize(): ?int
    {
        return $this->currentResponse?->json('maxPageSize');
    }
}
