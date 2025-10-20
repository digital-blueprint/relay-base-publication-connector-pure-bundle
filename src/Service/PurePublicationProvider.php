<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Service;

use Dbp\Relay\BasePublicationBundle\API\PublicationProviderInterface;
use Dbp\Relay\BasePublicationBundle\Entity\Publication as BasePublication;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Locale\Locale;
use Symfony\Component\HttpFoundation\Response;

class PurePublicationProvider implements PublicationProviderInterface
{
    private PublicationService $publicationService;

    public function __construct(PublicationService $publicationService)
    {
        $this->publicationService = $publicationService;
    }

    /**
     * @throws ApiError
     */
    public function getPublicationById(string $identifier, array $options = []): BasePublication
    {
        // Split identifier into idSource and value if it contains "_"
        $idSource = null;
        $value = $identifier;

        if (str_contains($identifier, '_')) {
            [$idSource, $value] = explode('_', $identifier, 2);
        }

        // Call connector service with split values
        $publication = $this->publicationService->getPublicationByIdFromSource($idSource, $value);

        if ($publication === null) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND,
                "Publication with identifier '$identifier' not found",
                'publication:not-found',
                [$identifier]);
        }

        return $this->mapToBasePublication($publication, $options);
    }

    /**
     * @throws ApiError
     */
    public function getPublications(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): array
    {
        $filters = [];
        if (isset($options['search'])) {
            $filters['search'] = (string) $options['search'];
        } elseif (isset($options['title'])) {
            $filters['search'] = (string) $options['title'];
        }

        // Pagination
        $offset = ($currentPageNumber - 1) * $maxNumItemsPerPage;
        // Fetch already mapped Publication objects
        $purePublicationsData = $this->publicationService->getPublications(
            $filters,
            $maxNumItemsPerPage,
            $offset
        );

        // Map to BasePublication for API
        return array_map(
            fn(\Dbp\Relay\BasePublicationConnectorPureBundle\Entity\Publication $pub) => $this->mapToBasePublication($pub, $options),
            $purePublicationsData
        );
    }


    private function mapToBasePublication(\Dbp\Relay\BasePublicationConnectorPureBundle\Entity\Publication $publication, array $options = []): BasePublication
    {
        $basePublication = new BasePublication();
        $basePublication->setIdentifier($publication->getIdentifier());

        // map UUID if supported
        if (method_exists($basePublication, 'setUuid')) {
            $uuid = $publication->getUuid();
            // error_log("MAPPING UUID: " . ($uuid ?? 'null'));
            $basePublication->setUuid($uuid);
        }

        // Handle language option for title and abstract
        $language = $options[Locale::LANGUAGE_OPTION] ?? 'en';

        // Map title to name (base publication entity uses setName instead of setTitle)
        if (method_exists($basePublication, 'setName')) {
            $basePublication->setName($publication->getTitle());
        } elseif (method_exists($basePublication, 'setTitle')) {
            $basePublication->setTitle($publication->getTitle());
        }

        // Map description/abstract
        if (method_exists($basePublication, 'setDescription')) {
            $basePublication->setDescription($publication->getAbstract());
        }

        // Map URL
        if (method_exists($basePublication, 'setUrl')) {
            $basePublication->setUrl($publication->getUrl());
        }

        // Map DOI
        if (method_exists($basePublication, 'setDoi')) {
            $basePublication->setDoi($publication->getDoi());
        }

        // Map publication date
        if (method_exists($basePublication, 'setPublicationDate')) {
            $basePublication->setPublicationDate($publication->getPublicationDate());
        }

        // Map authors - try different methods to include author information
        $authors = $publication->getAuthors();
        if (!empty($authors)) {
            // Method 1: Set as formatted string if the base entity supports setAuthors
            if (method_exists($basePublication, 'setAuthors')) {
                $basePublication->setAuthors($publication->getAuthorsFormatted());
            }

            // Method 2: Set as custom field if available
            if (method_exists($basePublication, 'setAuthor')) {
                $basePublication->setAuthor($publication->getAuthorsFormatted());
            }

            // Method 3: Add to description if authors field doesn't exist
            if (!method_exists($basePublication, 'setAuthors') && !method_exists($basePublication, 'setAuthor')) {
                $currentDescription = $basePublication->getDescription() ?? '';
                $authorsText = 'Authors: '.$publication->getAuthorsFormatted();
                $basePublication->setDescription($currentDescription."\n\n".$authorsText);
            }
        }

        return $basePublication;
    }
}
