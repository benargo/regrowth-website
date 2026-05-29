<?php

namespace App\Http\Integrations\Blizzard\Requests\Media;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Concerns\HasCaching;
use App\Http\Integrations\Blizzard\Pagination\MediaSearchPaginator;
use InvalidArgumentException;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\HasRequestPagination;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Saloon\PaginationPlugin\Paginator;

class SearchMediaRequest extends Request implements Cacheable, HasRequestPagination, Paginatable
{
    use HasCaching;

    protected Method $method = Method::GET;

    public function __construct(
        protected array $tags,
        protected ?int $itemId = null,
        protected ?string $name = null,
        protected ?string $orderby = null,
        protected ?int $page = null,
        protected ?int $pageSize = null,
    ) {
        if (count($tags) === 0) {
            throw new InvalidArgumentException('The "tags" parameter is required for media search.');
        }

        $invalid = array_diff($tags, GetMediaRequest::VALID_MEDIA_TAGS);
        if (count($invalid) > 0) {
            throw new InvalidArgumentException(sprintf(
                'Invalid tag(s): %s. Allowed tags are: %s',
                implode(', ', $invalid),
                implode(', ', GetMediaRequest::VALID_MEDIA_TAGS),
            ));
        }
    }

    public function resolveEndpoint(): string
    {
        return '/data/wow/search/media';
    }

    public function boot(PendingRequest $pendingRequest): void
    {
        /** @var BlizzardConnector $connector */
        $connector = $pendingRequest->getConnector();

        $pendingRequest->headers()->add(
            'Battlenet-Namespace',
            $connector->namespace('static'),
        );
    }

    protected function defaultQuery(): array
    {
        $query = ['tags' => implode(',', $this->tags)];

        if ($this->itemId !== null) {
            $query['itemId'] = $this->itemId;
        }
        if ($this->name !== null) {
            $query['name.en_US'] = $this->name;
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
        return 3600;
    }

    public function paginate(Connector $connector): Paginator
    {
        return new MediaSearchPaginator(connector: $connector, request: $this);
    }
}
