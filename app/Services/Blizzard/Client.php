<?php

namespace App\Services\Blizzard;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class Client
{
    protected Region $region;

    protected string $locale;

    protected ?string $namespace;

    public function __construct(
        protected string $clientId,
        protected string $clientSecret,
        ?Region $region = null,
        ?string $locale = null,
        ?string $namespace = null,
    ) {
        $this->region = $region ?? Region::from(config('services.blizzard.region', 'eu'));
        $this->locale = $locale ?? config('services.blizzard.locale', $this->region->defaultLocale());
        $this->namespace = $namespace;
        $this->validateLocale($this->locale);
    }

    /**
     * Create a client instance from application config.
     */
    public static function fromConfig(): self
    {
        return new self(
            clientId: config('services.blizzard.client_id'),
            clientSecret: config('services.blizzard.client_secret'),
            region: Region::from(config('services.blizzard.region', 'eu')),
            locale: config('services.blizzard.locale'),
        );
    }

    public function getRegion(): Region
    {
        return $this->region;
    }

    public function setRegion(Region $region): self
    {
        $this->region = $region;

        if (! $this->region->supportsLocale($this->locale)) {
            $this->locale = $this->region->defaultLocale();
        }

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->validateLocale($locale);
        $this->locale = $locale;

        return $this;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function withNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    public function http(): PendingRequest
    {
        $request = Http::baseUrl($this->region->apiUrl())
            ->withToken($this->getAccessToken())
            ->withQueryParameters(['locale' => $this->locale])
            ->acceptJson();

        if ($this->namespace) {
            $request->withHeaders(['Battlenet-Namespace' => $this->namespace]);
        }

        return $request;
    }

    public function getAccessToken(): string
    {
        $cacheKey = "blizzard_access_token_{$this->region->value}";

        $token = Cache::get($cacheKey);

        if ($token === null) {
            $accessToken = $this->requestAccessToken();

            Cache::put($cacheKey, $accessToken->token, $accessToken->expiresIn);

            return $accessToken->token;
        }

        return $token;
    }

    protected function requestAccessToken(): AccessToken
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post($this->region->tokenUrl(), [
                'grant_type' => 'client_credentials',
            ])
            ->throw();

        return AccessToken::fromResponse($response->json());
    }

    protected function validateLocale(string $locale): void
    {
        if (! $this->region->supportsLocale($locale)) {
            throw new InvalidArgumentException(sprintf(
                'Locale "%s" is not supported for region "%s". Supported locales: %s',
                $locale,
                $this->region->value,
                implode(', ', $this->region->locales()),
            ));
        }
    }
}
