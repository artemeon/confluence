<?php

declare(strict_types=1);

namespace Artemeon\Confluence\Endpoint;

class Auth
{
    private string $username;
    private string $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function getAuthenticationArray(): array
    {
        return ['auth' => [$this->username, $this->password]];
    }
}