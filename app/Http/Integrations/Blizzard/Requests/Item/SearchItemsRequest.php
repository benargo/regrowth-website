<?php

namespace App\Http\Integrations\Blizzard\Requests\Item;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Concerns\HasCaching;
use App\Http\Integrations\Blizzard\Pagination\SearchPaginator;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\HasRequestPagination;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Saloon\PaginationPlugin\Paginator;

class SearchItemsRequest extends Request implements Cacheable, HasRequestPagination, Paginatable
{
    use HasCaching;

    protected Method $method = Method::GET;

    private string $locale = 'en_GB';

    public function __construct(
        protected ?string $name = null,
        protected ?string $orderby = null,
        protected ?int $page = null,
        protected ?int $pageSize = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/data/wow/search/item';
    }

    public function boot(PendingRequest $pendingRequest): void
    {
        /** @var BlizzardConnector $connector */
        $connector = $pendingRequest->getConnector();

        $this->locale = $connector->getLocale();

        $pendingRequest->headers()->add(
            'Battlenet-Namespace',
            $connector->namespace('static'),
        );
    }

    protected function defaultQuery(): array
    {
        $query = [];

        if ($this->name !== null) {
            $query["name.{$this->locale}"] = $this->name;
        }

        if ($this->orderby !== null) {
            $query['orderby'] = $this->orderby;
        }

        if ($this->page !== null) {
            $query['_page'] = $this->page;
        }

        if ($this->pageSize !== null) {
            $query['_pageSize'] = min($this->pageSize, 1000);
        }

        return $query;
    }

    public function cacheExpiryInSeconds(): int
    {
        return 3600; // 1 hour
    }

    public function paginate(Connector $connector): Paginator
    {
        return new SearchPaginator(connector: $connector, request: $this);
    }
}
