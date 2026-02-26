<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\DataProvider;

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Service;

use Dbp\Relay\BasePublicationBundle\API\PublicationProviderInterface;
use Dbp\Relay\BasePublicationBundle\Entity\Publication;
use Dbp\Relay\BasePublicationConnectorPureBundle\Event\PublicationPostEvent;
use Dbp\Relay\BasePublicationConnectorPureBundle\Event\PublicationPreEvent;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\LocalData\LocalDataEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

class PurePublicationProvider implements PublicationProviderInterface
{
    private PublicationService $publicationService;
    private LocalDataEventDispatcher $eventDispatcher;

    public function __construct(
        PublicationService $publicationService,
        EventDispatcherInterface $dispatcher // Inject Symfony's EventDispatcher
    ) {
        $this->publicationService = $publicationService;
        $this->eventDispatcher = new LocalDataEventDispatcher(
            Publication::class,
            $dispatcher
        );
        // $this->eventDispatcher = new LocalDataEventDispatcher(BasePublication::class, $dispatcher);
    }

    // Add setter method if needed later
    public function setEventDispatcher($eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws ApiError
     */
    public function getPublicationById(string $identifier, array $options = []): Publication
    {
        // 1. Notify dispatcher
        $this->eventDispatcher?->onNewOperation($options);

        // 2. Pre-event (optional but fine)
        $preEvent = new PublicationPreEvent($options, $identifier);
        $this->eventDispatcher?->dispatch($preEvent);

        $options = $preEvent->getOptions();
        $identifier = $preEvent->getIdentifier() ?? $identifier;

        // 3. Parse Relay identifier → PURE source + value
        $idSource = null;
        $value = $identifier;

        if (str_contains($identifier, '_')) {
            [$idSource, $value] = explode('_', $identifier, 2);
        }

        // 4. Fetch PURE publication (PURE object)
        $purePublication = $this->publicationService
            ->getPublicationByIdFromSource($idSource, $value);

        if ($purePublication === null) {
            throw ApiError::withDetails(
                Response::HTTP_NOT_FOUND,
                "Publication with identifier '$identifier' not found",
                'publication:not-found',
                [$identifier]
            );
        }

        // 4. Create base publication
        $basePublication = new Publication();
        $basePublication->setIdentifier($purePublication->getIdentifier());
        // Set basic fields
        if (method_exists($purePublication, 'getName') && $purePublication->getName()) {
            $basePublication->setName($purePublication->getName());
        } elseif (method_exists($purePublication, 'getTitle') && $purePublication->getTitle()) {
            $basePublication->setName($purePublication->getTitle());
        } else {
            $basePublication->setName('Publication '.$purePublication->getIdentifier());
        }

        if (method_exists($purePublication, 'getUuid') && $purePublication->getUuid()) {
            $basePublication->setUuid($purePublication->getUuid());
        }

        // 5. Fetch PURE raw data using PURE VALUE ONLY
        $rawData = $this->publicationService
            ->tryGetItemDataFromPureBySourceId($value);

        // 6. Map PURE → BASE
        $basePublication = $this->mapToBasePublication($purePublication, $options);

        // 7. Attach local data DIRECTLY
        if ($rawData !== null) {
            $basePublication->setLocalData($rawData);
        }

        // 8. Optional post-event (now actually meaningful)
        if ($rawData !== null) {
            $postEvent = new PublicationPostEvent($basePublication, $rawData);
            $this->eventDispatcher?->dispatch($postEvent);
        }

        return $basePublication;
    }

    /**
     * @throws ApiError
     */
    public function getPublications(
        int $currentPageNumber,
        int $maxNumItemsPerPage,
        array $options = []
    ): array {
        $basePublications = [];

        // 1. Notify event dispatcher
        $this->eventDispatcher->onNewOperation($options);

        // 2. Dispatch pre-event
        $preEvent = new PublicationPreEvent($options);
        $this->eventDispatcher->dispatch($preEvent);
        $options = $preEvent->getOptions();

        $filters = [];

        if (!empty($options['search'])) {
            $filters['search'] = (string) $options['search'];
        }

        $filters['page'] = $currentPageNumber;
        $filters['perPage'] = $maxNumItemsPerPage;

        // Fetch publications WITH raw data
        $publicationsWithData = $this->publicationService->getPublicationsWithRawData($filters);

        foreach ($publicationsWithData as $item) {
            // Access as ARRAY (debug confirms it's an array)
            $publication = $item['publication'];
            $rawData = $item['rawData'];

            // Publication is already populated, just dispatch post event
            if ($rawData !== null) {
                $postEvent = new PublicationPostEvent($publication, $rawData, $options);
                $this->eventDispatcher->dispatch($postEvent);
            }

            $basePublications[] = $publication;
        }

        return $basePublications;
    }

    /**
     * Helper method to get raw publication data from Pure API
     * This is needed for the event dispatcher.
     */
    public function getRawPublicationData(string $identifier): ?array
    {
        // return $this->publicationService->getRawPublicationData($identifier);
        // return $this->publicationService->tryGetItemDataFromPureBySourceId($identifier);
        return $this->publicationService->tryGetItemDataFromPureBySourceId($identifier);
    }

    private function mapToBasePublication(Publication $publication, array $options = []): Publication
    {
        // IMPORTANT:
        // $publication is ALREADY a BasePublication
        // Do NOT create a new instance

        // Ensure identifier exists (should already be set)
        if ($publication->getIdentifier() === null) {
            throw new \LogicException('BasePublication has no identifier');
        }

        // Ensure UUID exists if available
        if ($publication->getUuid() === null && isset($options['uuid'])) {
            $publication->setUuid($options['uuid']);
        }

        // Ensure name exists
        if ($publication->getName() === null) {
            $publication->setName('Untitled Publication');
        }

        // Authors: only set if missing and available via options
        if ($publication->getAuthors() === null && isset($options['authors'])) {
            $publication->setAuthors($options['authors']);
        }

        // DO NOT touch:
        // - localData
        // - localDataValue
        // - identifier
        // - uuid if already set

        return $publication;
    }
}
