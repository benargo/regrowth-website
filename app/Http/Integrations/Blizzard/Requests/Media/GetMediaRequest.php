<?php

namespace App\Http\Integrations\Blizzard\Requests\Media;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Concerns\HasCaching;
use App\Http\Integrations\Blizzard\Responses\GetMediaResponse;
use InvalidArgumentException;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;

class GetMediaRequest extends Request implements Cacheable
{
    use HasCaching;

    protected ?string $response = GetMediaResponse::class;

    public const VALID_MEDIA_TAGS = ['item', 'spell', 'playable-class'];

    protected Method $method = Method::GET;

    public function __construct(
        protected string $tag,
        protected int $mediaId,
    ) {
        if (! in_array($tag, self::VALID_MEDIA_TAGS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid tag "%s". Allowed tags are: %s',
                $tag,
                implode(', ', self::VALID_MEDIA_TAGS),
            ));
        }
    }

    public function resolveEndpoint(): string
    {
        return "/data/wow/media/{$this->tag}/{$this->mediaId}";
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

    public function cacheExpiryInSeconds(): int
    {
        return 604800; // 7 days
    }
}
