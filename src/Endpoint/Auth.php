<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Endpoint;

class Auth
{
    private string $username;
    private string $apiToken;

    public function __construct(string $username, string $apiToken)
    {
        $this->username = $username;
        $this->apiToken = $apiToken;
    }

    /**
     * @return array{auth:array{string,string}}
     */
    public function getAuthenticationArray(): array
    {
        return ['auth' => [$this->username, $this->apiToken]];
    }
}
