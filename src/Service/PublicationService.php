<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Service;

use Dbp\Relay\BasePublicationBundle\Entity\Publication;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Http\Connection;
use Dbp\Relay\CoreBundle\Http\ConnectionException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PublicationService
{
    private const PUBLICATIONS_PATH = 'research-outputs';
    private const API_KEY_HEADER_NAME = 'api-key';

    private const PURE_REQUEST_FAILED_ERROR_ID = 'publication:pure-request-failed';
    private const INVALID_RESPONSE_FORMAT_ERROR_ID = 'publication:invalid-response-format';

    private const UUID_PURE_ATTRIBUTE = 'uuid';
    private const TITLE_PURE_ATTRIBUTE = 'title';
    private const IDENTIFIERS_PURE_ATTRIBUTE = 'identifiers';
    private const RESULT_ITEMS_PURE_ATTRIBUTE = 'items';
    private const SEARCH_STRING_PURE_QUERY_PARAMETER = 'searchString';
    private const PAGE_SIZE_PURE_QUERY_PARAMETER = 'size';
    private const PAGE_NUMBER_PURE_QUERY_PARAMETER = 'offset';

    private Config $config;
    private ?Connection $connection = null;

    public function __construct(Config $config, private EventDispatcherInterface $eventDispatcher)
    {
        $this->config = $config;
    }

    /**
     * @throws ApiError
     */
    public function checkConnection(): void
    {
        $this->tryGetAndDecodeDataFromPureApi(self::PUBLICATIONS_PATH);
    }

    /**
     * @throws ApiError
     */
    public function getPublicationById(string $identifier): ?Publication
    {
        $publicationData = $this->tryGetItemDataFromPureBySourceId($identifier);

        if ($publicationData === null) {
            return null;
        }

        $pub = new Publication();
        $pub->setIdentifier($this->tryGetSourceIdFromItem($publicationData));
        $pub->setName($this->extractTitle($publicationData) ?? 'Untitled Publication');
        $pub->setUuid($publicationData[self::UUID_PURE_ATTRIBUTE] ?? null);

        return $pub;
    }

    /**
     * @throws ApiError
     */
    public function getPublications(array $filters = [], int $limit = 1000): array
    {
        // TODO: fix the perPage logic

        $searchString = $filters['search'] ?? '';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, (int) ($filters['perPage'] ?? 50));
        $perPage = min($perPage, $limit);

        $results = $this->searchPureApi($searchString, $perPage, $page);

        // Map each item only once and only required fields
        $publications = [];
        foreach ($results as $itemData) {
            $identifier = $this->tryGetSourceIdFromItem($itemData);
            $title = $this->extractTitle($itemData) ?? 'Untitled Publication';
            $uuid = $itemData[self::UUID_PURE_ATTRIBUTE] ?? null;

            $publication = new Publication();

            $publication->setIdentifier($identifier);
            // $publication->setName($title);
            $publication->setUuid($uuid);

            $publications[] = $publication;
        }

        return $publications;

        /*
        // Get page and perPage from filters, with defaults
        $page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $perPage = isset($filters['perPage']) ? max(1, (int) $filters['perPage']) : 50;

        // Ensure we do not exceed the limit
        $perPage = min($perPage, $limit);

        $results = $this->searchPureApi($searchString, $perPage, $page);

        // Only map minimal data: identifier + title
        return array_map(fn (array $itemData) => $this->mapPureItemDataToPublication($itemData), $results);
        */
    }

    // In PublicationService.php
    public function getPublicationsWithRawData(array $filters = [], int $limit = 1000): array
    {
        $searchString = $filters['search'] ?? '';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, (int) ($filters['perPage'] ?? 50));
        $perPage = min($perPage, $limit);

        $results = $this->searchPureApi($searchString, $perPage, $page);

        $publicationsWithData = [];
        foreach ($results as $itemData) {
            $identifier = $this->tryGetSourceIdFromItem($itemData);
            if (!$identifier) {
                continue;
            }

            $title = $this->extractTitle($itemData) ?? 'Untitled Publication';
            $uuid = $itemData[self::UUID_PURE_ATTRIBUTE] ?? null;

            // Create CONNECTOR Publication object, not BASE Publication
            $publication = new Publication();
            $publication->setIdentifier($identifier);
            $publication->setName($title);
            $publication->setUuid($uuid);

            $publicationsWithData[] = [
                'publication' => $publication,  // Connector Publication
                'rawData' => $itemData,
            ];
        }

        return $publicationsWithData;
    }

    /**
     * Get a publication by idSource and value.
     * If $idSource is null, fallback to matching only by value or UUID.
     */
    public function getPublicationByIdFromSource(?string $idSource, string $value): ?Publication
    {
        foreach ($this->searchPureApi($value, 5) as $itemData) { // limit search results for speed
            $primaryId = $this->tryGetSourceIdFromItem($itemData);

            if ($idSource !== null) {
                if ($primaryId === $idSource.'_'.$value) {
                    return $this->mapPureDataToPublication($itemData, $primaryId);
                }
            } else {
                if ($primaryId === $value || ($itemData['uuid'] ?? null) === $value) {
                    return $this->mapPureDataToPublication($itemData, $primaryId ?? $value);
                }
            }
        }

        return null;
    }

    private function buildSearchParameters(array $filters): array
    {
        $searchParams = [];

        // Text search (searches in title, abstract, etc.)
        if (isset($filters['search'])) {
            $searchParams[self::SEARCH_STRING_PURE_QUERY_PARAMETER] = $filters['search'];
        }

        return $searchParams;
    }

    public function mapPureDataToPublication(array $publicationData, string $identifier): Publication
    {
        $publication = new Publication();
        $publication->setIdentifier($identifier);

        $titleValue = $publicationData['title'] ?? null;

        if (is_array($titleValue)) {
            $publication->setName($titleValue['value'] ?? $titleValue['en_GB'] ?? $titleValue['de_DE'] ?? current($titleValue));
        } else {
            $publication->setName($titleValue ?? 'Untitled Publication');
        }

        $publication->setUuid($publicationData[self::UUID_PURE_ATTRIBUTE] ?? null);
        /*
        // $publication->setUuid($uuid);
        $publication->setUuid($publicationData[self::UUID_PURE_ATTRIBUTE] ?? null);
        // error_log('PUBLICATION DATA: ' . json_encode($publicationData));

        // Map title - handle both string and array formats
        $title = $this->extractTitle($publicationData);
        $publication->setTitle($title);

        // Map DOI
        $publication->setDoi($this->extractDoi($publicationData));

        // Map publication date
        if (isset($publicationData['publicationDate'])) {
            $publication->setPublicationDate($publicationData['publicationDate']);
        }

        // Map abstract - handle both string and array formats
        $abstract = $this->extractAbstract($publicationData);
        $publication->setAbstract($abstract);

        // Map authors - try different possible locations for author data
        $authors = $this->extractAuthors($publicationData);
        $publication->setAuthors($authors);*/

        return $publication;
    }

    private function extractTitle(array $publicationData): ?string
    {
        if (!isset($publicationData[self::TITLE_PURE_ATTRIBUTE])) {
            return null;
        }

        $title = $publicationData[self::TITLE_PURE_ATTRIBUTE];

        // Handle array format with 'value' key
        if (is_array($title) && isset($title['value'])) {
            return $title['value'];
        }

        // Handle localized titles (array with language codes)
        if (is_array($title)) {
            // Return German title if available, otherwise English, otherwise first available
            return $title['de_DE'] ?? $title['en_GB'] ?? $title['en_US'] ?? current($title);
        }

        // Handle string format
        return $title;
    }

    private function extractAbstract(array $publicationData): ?string
    {
        if (!isset($publicationData['abstract'])) {
            return null;
        }

        $abstract = $publicationData['abstract'];

        // Handle array format with 'value' key
        if (is_array($abstract) && isset($abstract['value'])) {
            return $abstract['value'];
        }

        // Handle localized abstracts (array with language codes)
        if (is_array($abstract)) {
            // Return German abstract if available, otherwise English, otherwise first available
            return $abstract['de_DE'] ?? $abstract['en_GB'] ?? $abstract['en_US'] ?? current($abstract);
        }

        // Handle string format
        return $abstract;
    }

    private function extractDoi(array $publicationData): ?string
    {
        if (!isset($publicationData[self::IDENTIFIERS_PURE_ATTRIBUTE])) {
            return null;
        }

        foreach ($publicationData[self::IDENTIFIERS_PURE_ATTRIBUTE] as $identifier) {
            if (($identifier['type']['uri'] ?? '') === '/dk/atira/pure/researchoutput/identifiers/doi') {
                return $identifier['value'] ?? null;
            }
        }

        return null;
    }

    private function extractAuthors(array $publicationData): array
    {
        $authors = [];

        if (!isset($publicationData['contributors'])) {
            return $authors;
        }

        foreach ($publicationData['contributors'] as $contributor) {
            $author = $this->extractAuthorFromContributor($contributor);
            if ($author !== null) {
                $authors[] = $author;
            }
        }

        return $authors;
    }

    private function extractAuthorFromContributor(array $contributor): ?array
    {
        // Check if this contributor has an author role
        $roleUri = $contributor['role']['uri'] ?? '';
        $isAuthor = str_contains($roleUri, '/author');

        if (!$isAuthor) {
            return null; // Skip non-author contributors
        }

        // Extract name from internal person
        if (isset($contributor['person']) && isset($contributor['name']['firstName']) && isset($contributor['name']['lastName'])) {
            return [
                'firstName' => $contributor['name']['firstName'],
                'lastName' => $contributor['name']['lastName'],
                'role' => $contributor['role']['term']['en_GB'] ?? 'Author',
                'type' => 'internal',
            ];
        }

        // Extract name from external person
        if (isset($contributor['externalPerson']) && isset($contributor['name']['firstName']) && isset($contributor['name']['lastName'])) {
            return [
                'firstName' => $contributor['name']['firstName'],
                'lastName' => $contributor['name']['lastName'],
                'role' => $contributor['role']['term']['en_GB'] ?? 'Author',
                'type' => 'external',
            ];
        }

        // Fallback: try to get name directly from contributor
        if (isset($contributor['name']['firstName']) && isset($contributor['name']['lastName'])) {
            return [
                'firstName' => $contributor['name']['firstName'],
                'lastName' => $contributor['name']['lastName'],
                'role' => $contributor['role']['term']['en_GB'] ?? 'Author',
                'type' => 'unknown',
            ];
        }

        return null;
    }

    private function extractAuthorFromPersonAssociation(array $association): ?array
    {
        if (isset($association['person']['name']['firstName'])
            && isset($association['person']['name']['lastName'])) {
            return [
                'firstName' => $association['person']['name']['firstName'],
                'lastName' => $association['person']['name']['lastName'],
                'role' => $association['role']['term']['en_GB'] ?? 'Author',
            ];
        }

        return null;
    }

    public function tryGetItemDataFromPureBySourceId(string $identifier): ?array
    {
        // Extract source/value if composite identifier is used
        $idSource = null;
        $value = $identifier;

        if (str_contains($identifier, '_')) {
            [$idSource, $value] = explode('_', $identifier, 2);
        }

        // Search with value only (Pure understands this better)
        $results = $this->searchPureApi($value, 10);

        foreach ($results as $itemData) {
            // 1) Match via extracted Pure source identifier
            $primaryId = $this->tryGetSourceIdFromItem($itemData);
            if ($primaryId === $identifier) {
                return $itemData;
            }

            // 2) Match via UUID (VERY IMPORTANT)
            if (
                isset($itemData[self::UUID_PURE_ATTRIBUTE])
                && $itemData[self::UUID_PURE_ATTRIBUTE] === $value
            ) {
                return $itemData;
            }

            // 3) Match by idSource + value
            if ($idSource !== null) {
                foreach ($itemData[self::IDENTIFIERS_PURE_ATTRIBUTE] ?? [] as $id) {
                    if (
                        ($id['idSource'] ?? null) === $idSource
                        && ($id['value'] ?? null) === $value
                    ) {
                        return $itemData;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Map raw Pure API item data to Publication entity.
     */
    public function mapPureItemDataToPublication(array $itemData): Publication
    {
        $identifier = $this->tryGetSourceIdFromItem($itemData);

        return $this->mapPureDataToPublication($itemData, $identifier);
    }

    public function tryGetSourceIdFromItem(array $itemData): ?string
    {
        $primaryId = null;

        // First try to find TUGo identifier (preferred)
        foreach ($itemData[self::IDENTIFIERS_PURE_ATTRIBUTE] ?? [] as $identifierItem) {
            if (($identifierItem['idSource'] ?? '') === 'TUGo@tugraz.at') {
                $primaryId = $identifierItem['value'] ?? null;
                if ($primaryId !== null) {
                    return $primaryId;
                }
            }
        }

        // If no TUGo identifier, use the first available identifier as fallback
        foreach ($itemData[self::IDENTIFIERS_PURE_ATTRIBUTE] ?? [] as $identifierItem) {
            $idSource = $identifierItem['idSource'] ?? '';
            $value = $identifierItem['value'] ?? null;

            if ($value !== null && $idSource !== '') {
                // Create a composite identifier: source_value
                return $idSource.'_'.$value;
            }
        }

        // If no identifiers at all, use UUID as last resort
        if (isset($itemData[self::UUID_PURE_ATTRIBUTE])) {
            return 'uuid_'.$itemData[self::UUID_PURE_ATTRIBUTE];
        }

        return null;
    }

    /**
     * Wrapper for Pure API search
     * Accepts a string, returns array of results.
     */
    private function searchPureApi(string $searchString, int $size = 50, int $page = 1): array
    {
        try {
            $offset = ($page - 1) * $size; // <-- calculate offset from page

            $data = [
                self::SEARCH_STRING_PURE_QUERY_PARAMETER => $searchString,
                self::PAGE_SIZE_PURE_QUERY_PARAMETER => $size,
                self::PAGE_NUMBER_PURE_QUERY_PARAMETER => $offset,
                'fields' => [self::UUID_PURE_ATTRIBUTE, self::TITLE_PURE_ATTRIBUTE, self::IDENTIFIERS_PURE_ATTRIBUTE], // only fetch required fields
            ];

            $response = $this->getConnection()->postJSON(
                self::PUBLICATIONS_PATH.'/search',
                $data,
                $this->getPureApiRequestOptions()
            );

            $json = $this->decodeJson($response->getBody()->getContents());

            return $json[self::RESULT_ITEMS_PURE_ATTRIBUTE] ?? [];
        } catch (ConnectionException $connectionException) {
            throw $this->dispatchConnectionException($connectionException, 'Failed to search Pure publications API');
        }
    }

    /**
     * @throws ApiError
     */
    private function tryGetAndDecodeDataFromPureApi(string $uri, array $queryParameters = [], string $errorMessage = 'Failed to get data from Pure API'): ?array
    {
        try {
            $response = $this->getConnection()->get($uri, $queryParameters, $this->getPureApiRequestOptions());

            if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
                return null;
            }

            return $this->decodeJson($response->getBody()->getContents());
        } catch (ConnectionException $connectionException) {
            if ($connectionException->getCode() === Response::HTTP_NOT_FOUND) {
                return null;
            }
            throw $this->dispatchConnectionException($connectionException, $errorMessage);
        }
    }

    private function getPureApiRequestOptions(): array
    {
        return [
            Connection::REQUEST_OPTION_HEADERS => [
                self::API_KEY_HEADER_NAME => $this->config->getPureApiKey(),
            ],
        ];
    }

    private function dispatchConnectionException(ConnectionException $connectionException, string $errorMessage): ApiError
    {
        return ApiError::withDetails(Response::HTTP_BAD_GATEWAY, $errorMessage,
            self::PURE_REQUEST_FAILED_ERROR_ID, [$connectionException->getCode(), $connectionException->getMessage()]);
    }

    /**
     * @throws ApiError
     */
    private function decodeJson(string $contents): array
    {
        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw ApiError::withDetails(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Pure API returned invalid JSON structure',
                self::INVALID_RESPONSE_FORMAT_ERROR_ID
            );
        }

        return $data;
    }

    public function getConnection(): Connection
    {
        if ($this->connection === null) {
            $apiUrl = $this->config->getPureApiUrl();

            // Ensure the URL ends with a slash for proper concatenation
            if (substr($apiUrl, -1) !== '/') {
                $apiUrl .= '/';
            }

            $connection = new Connection($apiUrl);
            $this->connection = $connection;
        }

        return $this->connection;
    }

    /**
     * Debug method to check author data in API response.
     */
    public function debugAuthorData(string $searchString): array
    {
        // First, let's see what we get with a blank search
        $allItems = $this->searchPureApi([], 20);

        if (empty($allItems)) {
            return ['error' => 'No publications found at all in API'];
        }

        $debugInfo = [
            'total_publications_found' => count($allItems),
            'first_publication_identifier' => $this->tryGetSourceIdFromItem($allItems[0]),
            'publications_matching_search' => [],
        ];

        // Check if any publication matches our search string
        $matchingItems = [];
        foreach ($allItems as $item) {
            $identifier = $this->tryGetSourceIdFromItem($item);
            $title = $this->extractTitle($item);

            // Check if search string matches identifier or title
            if (stripos($identifier ?? '', $searchString) !== false
                || stripos($title ?? '', $searchString) !== false) {
                $matchingItems[] = $item;
            }
        }

        if (empty($matchingItems)) {
            $debugInfo['note'] = 'No exact match found, showing first publication instead';
            $matchingItems = [$allItems[0]];
        }

        foreach ($matchingItems as $index => $publicationData) {
            $publicationDebug = [
                'index' => $index,
                'publication_type' => $publicationData['typeDiscriminator'] ?? 'unknown',
                'title' => $this->extractTitle($publicationData),
                'identifier' => $this->tryGetSourceIdFromItem($publicationData),
                'all_fields' => array_keys($publicationData),
            ];

            // Check for ALL possible author-related fields
            $possibleAuthorFields = [
                'contributors', 'personsAssociations', 'personAssociations',
                'authors', 'person', 'people', 'persons', 'externalPersons',
                'personExternalAssociations', 'externalPersonAssociations',
            ];

            foreach ($possibleAuthorFields as $field) {
                if (isset($publicationData[$field])) {
                    $publicationDebug[$field] = [
                        'exists' => true,
                        'type' => gettype($publicationData[$field]),
                        'count' => is_array($publicationData[$field]) ? count($publicationData[$field]) : 'N/A',
                        'sample' => is_array($publicationData[$field]) && !empty($publicationData[$field]) ?
                            $publicationData[$field][0] : $publicationData[$field],
                    ];
                } else {
                    $publicationDebug[$field] = ['exists' => false];
                }
            }

            // Also show any field that contains "person" or "author" in the name
            $publicationDebug['other_related_fields'] = [];
            foreach ($publicationData as $key => $value) {
                if (stripos($key, 'person') !== false || stripos($key, 'author') !== false) {
                    $publicationDebug['other_related_fields'][$key] = [
                        'type' => gettype($value),
                        'sample' => is_array($value) && count($value) > 0 ? $value[0] : $value,
                    ];
                }
            }

            $debugInfo['publications_matching_search'][] = $publicationDebug;
        }

        return $debugInfo;
    }

    /**
     * Debug method to see all fields in the first publication.
     */
    public function debugFirstPublicationFields(): array
    {
        $items = $this->searchPureApi([], 1);
        if (empty($items)) {
            return ['error' => 'No publications found'];
        }

        $firstItem = $items[0];
        $fields = [];

        foreach ($firstItem as $key => $value) {
            $fields[$key] = [
                'type' => gettype($value),
                'sample' => is_array($value) ? array_slice($value, 0, 2) : $value,
            ];
        }

        return $fields;
    }

    /**
     * Temporary method to get config for debugging.
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Debug why publication is not found.
     */
    public function debugPublicationNotFound(string $identifier): array
    {
        $searchTerms = [
            'full_identifier' => $identifier,
            'numeric_part' => str_replace('researchoutputwizard_', '', $identifier),
        ];

        $results = [];

        foreach ($searchTerms as $type => $term) {
            $items = $this->searchPureApi([self::SEARCH_STRING_PURE_QUERY_PARAMETER => $term], 10);
            $results[$type] = [
                'search_term' => $term,
                'items_found' => count($items),
                'identifiers_found' => array_map([$this, 'tryGetSourceIdFromItem'], $items),
            ];
        }

        return $results;
    }
}
