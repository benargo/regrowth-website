<?php

namespace App\Http\Integrations\Blizzard\Exceptions;

use Saloon\Exceptions\Request\ClientException;
use Saloon\Http\Response;
use Saloon\XmlWrangler\XmlReader;
use Throwable;

class BlizzardXmlException extends ClientException implements BlizzardRequestException
{
    public readonly ?string $xmlCode;

    public readonly ?string $xmlMessage;

    public function __construct(
        public readonly string $method,
        public readonly string $endpoint,
        public readonly int $blizzardStatus,
        Response $response,
        XmlReader $reader,
        ?Throwable $previous = null,
    ) {
        $this->xmlCode = $reader->value('Error.Code')->sole() ?: null;
        $this->xmlMessage = $reader->value('Error.Message')->sole() ?: null;

        $message = "Blizzard API request failed: {$this->method} {$this->endpoint} (status {$this->blizzardStatus})";

        if ($this->xmlCode !== null) {
            $message .= " — {$this->xmlCode}";
        }

        if ($this->xmlMessage !== null) {
            $message .= ": {$this->xmlMessage}";
        }

        parent::__construct($response, $message, $this->blizzardStatus, $previous);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getBlizzardStatus(): int
    {
        return $this->blizzardStatus;
    }

    public function getBlizzardCode(): ?string
    {
        return $this->xmlCode;
    }

    /** @return array<string, mixed>|null */
    public function getBlizzardBody(): ?array
    {
        return array_filter(['code' => $this->xmlCode, 'message' => $this->xmlMessage]) ?: null;
    }
}
