<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Service;

class Config
{
    private array $config = [];

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getPureApiUrl(): string
    {
        return $this->config['pure']['api_url'];
    }

    public function getPureApiKey(): string
    {
        return $this->config['pure']['api_key'];
    }
}