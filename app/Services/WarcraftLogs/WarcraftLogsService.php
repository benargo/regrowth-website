<?php

namespace App\Services\WarcraftLogs;

use App\Services\WarcraftLogs\AuthenticationHandler;

class WarcraftLogsService
{
    protected $authHandler;

    public function __construct(array $config)
    {
        $this->authHandler = new AuthenticationHandler(
            $config['client_id'],
            $config['client_secret'],
            $config['token_url']
        );
    }

    public function auth(): AuthenticationHandler
    {
        return $this->authHandler;
    }
}