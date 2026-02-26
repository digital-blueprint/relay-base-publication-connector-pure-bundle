<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Event;

use Dbp\Relay\CoreBundle\LocalData\LocalDataPreEvent;

class PublicationPreEvent extends LocalDataPreEvent
{
    public function __construct(array $options, private ?string $identifier = null)
    {
        parent::__construct($options);
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }
}