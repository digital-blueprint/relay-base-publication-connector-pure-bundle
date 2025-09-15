<?php

declare(strict_types=1);

namespace Dbp\Relay\RelayBasePublicationConnectorPureBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;
use Dbp\Relay\RelayBasePublicationConnectorPureBundle\Entity\Publication;
use Dbp\Relay\RelayBasePublicationConnectorPureBundle\Service\PublicationService;

class PublicationProcessor extends AbstractDataProcessor
{
    private PublicationService $publicationService;

    public function __construct(PublicationService $publicationService)
    {
        parent::__construct();
        $this->publicationService = $publicationService;
    }

    protected function addItem(mixed $data, array $filters): Publication
    {
        assert($data instanceof Publication);

        $data->setIdentifier('42');

        return $this->publicationService->addPublication($data);
    }

    protected function removeItem($identifier, $data, array $filters): void
    {
        assert($data instanceof Publication);

        $this->publicationService->removePublication($data);
    }
}
