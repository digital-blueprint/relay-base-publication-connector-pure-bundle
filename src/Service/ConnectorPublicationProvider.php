<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Service;

use Dbp\Relay\BasePublicationBundle\API\PublicationProviderInterface;
use Dbp\Relay\BasePublicationBundle\Entity\Publication;

class ConnectorPublicationProvider implements PublicationProviderInterface
{
    public function getPublicationById(string $identifier, array $options = []): Publication
    {
        $publication = new Publication();
        $publication->setIdentifier($identifier);
        $publication->setName('Connector Stub Publication '.$identifier);

        return $publication;
    }

    public function getPublications(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): array
    {
        return [
            $this->getPublicationById('1'),
            $this->getPublicationById('2'),
            $this->getPublicationById('3'),
        ];
    }
}
