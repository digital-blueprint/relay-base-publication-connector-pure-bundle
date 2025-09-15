<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\BasePublicationConnectorPureBundle\Entity\Publication;
use Dbp\Relay\BasePublicationConnectorPureBundle\Service\PublicationService;

/**
 * @extends AbstractDataProvider<Publication>
 */
class PublicationProvider extends AbstractDataProvider
{
    private PublicationService $publicationService;

    public function __construct(PublicationService $publicationService)
    {
        parent::__construct();
        $this->publicationService = $publicationService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?Publication
    {
        return $this->publicationService->getPublication($id, $filters, $options);
    }

    /**
     * @return Publication[]
     */
    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        return $this->publicationService->getPublications($currentPageNumber, $maxNumItemsPerPage, $filters, $options);
    }
}
