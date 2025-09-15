<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Service;

use Dbp\Relay\BasePublicationConnectorPureBundle\Entity\Publication;

class PublicationService
{
    public function setConfig(array $config): void
    {
    }

    public function getPublication(string $identifier, array $filters = [], array $options = []): ?Publication
    {
        return null;
    }

    /**
     * @return Publication[]
     */
    public function getPublications(int $currentPageNumber, int $maxNumItemsPerPage, array $filters, array $options): array
    {
        return [];
    }

    public function addPublication(Publication $data): Publication
    {
        return $data;
    }

    public function removePublication(Publication $data): void
    {
    }
}
