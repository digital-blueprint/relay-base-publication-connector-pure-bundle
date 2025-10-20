<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Helpers\Tools;
use Dbp\Relay\CoreBundle\Http\Connection;
use Dbp\Relay\CoreBundle\Http\ConnectionException;
use Symfony\Component\HttpFoundation\Response;
use Dbp\Relay\BasePublicationBundle\Entity\Publication;

class PublicationApi
{
    private const PUBLICATIONS_PATH = 'research-outputs';
    private const API_KEY_HEADER_NAME = 'api-key';

    private const PURE_REQUEST_FAILED_ERROR_ID = 'publication:pure-request-failed';
    private const INVALID_RESPONSE_FORMAT_ERROR_ID = 'publication:invalid-response-format';
    private const PUBLICATION_NOT_FOUND_ERROR_ID = 'publication:not-found';

    private const UUID_PURE_ATTRIBUTE = 'uuid';
    private const TITLE_PURE_ATTRIBUTE = 'title';
    private const IDENTIFIERS_PURE_ATTRIBUTE = 'identifiers';
    private const RESULT_ITEMS_PURE_ATTRIBUTE = 'items';
    private const SEARCH_STRING_PURE_QUERY_PARAMETER = 'searchString';
    private const PAGE_SIZE_PURE_QUERY_PARAMETER = 'size';

    private Config $config;
    private ?Connection $connection = null;

    public function __construct(Config $config)
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
        // Split identifier into idSource and value if it contains underscore
        $idSource = null;
        $value = $identifier;

        if (str_contains($identifier, '_')) {
            [$idSource, $value] = explode('_', $identifier, 2);
        }

        $publicationData = $this->tryGetItemDataFromPureBySourceId($idSource, $value);

        if ($publicationData === null) {
            return null;
        }

        return $this->mapPureDataToPublication($publicationData, $identifier);
    }

    public function getPublicationByIdFromSource(?string $idSource, string $value): ?Publication
    {
        $publicationData = $this->tryGetItemDataFromPureBySourceId($idSource, $value);

        if ($publicationData === null) {
            return null;
        }

        // Use source_value format for the BasePublication identifier
        $identifier = $idSource ? $idSource.'_'.$value : $value;

        return $this->mapPureDataToPublication($publicationData, $identifier);
    }

    /**
     * @throws ApiError
     */
    public function getPublications(array $filters = []): array
    {
        $publications = [];
        $searchString = $filters['search'] ?? '';

        foreach ($this->searchPureApi($searchString) as $publicationData) {
            $identifier = $this->tryGetSourceIdFromItem($publicationData);
            if ($identifier !== null) {
                $publication = $this->mapPureDataToPublication($publicationData, $identifier);
                $publications[] = $publication;
            }
        }

        return $publications;
    }

    private function mapPureDataToPublication(array $publicationData, string $identifier): Publication
    {
        $publication = new Publication();
        $publication->setIdentifier($identifier);
        $publication->setUuid($publicationData[self::UUID_PURE_ATTRIBUTE]);

        // Map title
        if (isset($publicationData[self::TITLE_PURE_ATTRIBUTE])) {
            if (is_array($publicationData[self::TITLE_PURE_ATTRIBUTE])) {
                // Handle localized titles
                $publication->setTitle($publicationData[self::TITLE_PURE_ATTRIBUTE]['en_GB'] ??
                    $publicationData[self::TITLE_PURE_ATTRIBUTE]['de_DE'] ??
                    current($publicationData[self::TITLE_PURE_ATTRIBUTE]));
            } else {
                $publication->setTitle($publicationData[self::TITLE_PURE_ATTRIBUTE]);
            }
        }

        // Map DOI
        $publication->setDoi($this->extractDoi($publicationData));

        // Map publication date
        if (isset($publicationData['publicationDate'])) {
            $publication->setPublicationDate($publicationData['publicationDate']);
        }

        // Map publication type
        if (isset($publicationData['type']['uri'])) {
            $publication->setPublicationType($this->mapPublicationType($publicationData['type']['uri']));
        }

        // Map journal info
        if (isset($publicationData['journalAssociation']['journal']['title'])) {
            $publication->setJournal($publicationData['journalAssociation']['journal']['title']);
        }

        if (isset($publicationData['journalAssociation']['volume'])) {
            $publication->setVolume($publicationData['journalAssociation']['volume']);
        }

        if (isset($publicationData['journalAssociation']['issue'])) {
            $publication->setIssue($publicationData['journalAssociation']['issue']);
        }

        if (isset($publicationData['journalAssociation']['pages'])) {
            $publication->setPages($publicationData['journalAssociation']['pages']);
        }

        // Map abstract
        if (isset($publicationData['abstract'])) {
            if (is_array($publicationData['abstract'])) {
                $publication->setAbstract($publicationData['abstract']['en_GB'] ??
                    $publicationData['abstract']['de_DE'] ??
                    current($publicationData['abstract']));
            } else {
                $publication->setAbstract($publicationData['abstract']);
            }
        }

        // Map authors
        $authors = $this->extractAuthors($publicationData);
        $publication->setAuthors($authors);

        // Map keywords
        $keywords = $this->extractKeywords($publicationData);
        $publication->setKeywords($keywords);

        // Map URL
        if (isset($publicationData['electronicVersions'])) {
            $publication->setUrl($this->extractUrl($publicationData['electronicVersions']));
        }

        return $publication;
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

        if (!isset($publicationData['personsAssociations'])) {
            return $authors;
        }

        foreach ($publicationData['personsAssociations'] as $association) {
            if (isset($association['person']['name']['firstName'])
                && isset($association['person']['name']['lastName'])) {
                $authors[] = [
                    'firstName' => $association['person']['name']['firstName'],
                    'lastName' => $association['person']['name']['lastName'],
                    'role' => $association['role']['term']['en_GB'] ?? 'Author',
                ];
            }
        }

        return $authors;
    }

    private function extractKeywords(array $publicationData): array
    {
        $keywords = [];

        if (!isset($publicationData['keywordGroups'])) {
            return $keywords;
        }

        foreach ($publicationData['keywordGroups'] as $keywordGroup) {
            if (isset($keywordGroup['keywords'])) {
                foreach ($keywordGroup['keywords'] as $keyword) {
                    if (isset($keyword['freeKeywords'])) {
                        $keywords = array_merge($keywords, $keyword['freeKeywords']);
                    }
                }
            }
        }

        return $keywords;
    }

    private function extractUrl(array $electronicVersions): ?string
    {
        foreach ($electronicVersions as $version) {
            if (isset($version['file'])) {
                return $version['file']['url'] ?? null;
            }
        }

        return null;
    }

    private function mapPublicationType(string $typeUri): string
    {
        $mapping = [
            '/dk/atira/pure/researchoutput/researchoutputtypes/researchoutput/article' => 'Journal Article',
            '/dk/atira/pure/researchoutput/researchoutputtypes/researchoutput/conferencepaper' => 'Conference Paper',
            '/dk/atira/pure/researchoutput/researchoutputtypes/researchoutput/book' => 'Book',
            '/dk/atira/pure/researchoutput/researchoutputtypes/researchoutput/chapter' => 'Book Chapter',
            '/dk/atira/pure/researchoutput/researchoutputtypes/researchoutput/patent' => 'Patent',
        ];

        return $mapping[$typeUri] ?? 'Other';
    }

    /**
     * Try to get item data from Pure by idSource and value.
     *
     * @param string|null $idSource The source of the identifier (optional)
     * @param string      $value    The value of the identifier
     *
     * @throws ApiError
     */
    private function tryGetItemDataFromPureBySourceId(?string $idSource, string $value): ?array
    {
        foreach ($this->searchPureApi($value) as $itemData) {
            foreach ($itemData[self::IDENTIFIERS_PURE_ATTRIBUTE] ?? [] as $identifierItem) {
                $itemIdSource = $identifierItem['idSource'] ?? '';
                $itemValue = $identifierItem['value'] ?? '';

                // Match either exact value or source_value format
                if (($idSource !== null && $itemIdSource === $idSource && $itemValue === $value)
                    || ($idSource === null && $itemValue === $value)) {
                    return $itemData;
                }
            }
        }

        return null;
    }

    private function tryGetSourceIdFromItem(array $itemData): ?string
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
     * @return array the array of result items
     *
     * @throws ApiError
     */
    private function searchPureApi(string $searchString): array
    {
        try {
            $data = [
                self::SEARCH_STRING_PURE_QUERY_PARAMETER => $searchString,
                self::PAGE_SIZE_PURE_QUERY_PARAMETER => 1000,
            ];
            $response = $this->getConnection()->postJSON(self::PUBLICATIONS_PATH.'/search', $data, $this->getPureApiRequestOptions());
        } catch (ConnectionException $connectionException) {
            throw $this->dispatchConnectionException($connectionException, 'Failed to search Pure publications API');
        }

        return $this->decodeJson($response->getBody()->getContents())[self::RESULT_ITEMS_PURE_ATTRIBUTE];
    }

    /**
     * @throws ApiError
     */
    private function tryGetAndDecodeDataFromPureApi(string $uri, array $queryParameters = [], string $errorMessage = 'Failed to get data from Pure API'): ?array
    {
        try {
            $response = $this->getConnection()->get($uri, $queryParameters, $this->getPureApiRequestOptions());
        } catch (ConnectionException $connectionException) {
            if ($connectionException->getCode() === Response::HTTP_NOT_FOUND) {
                return null;
            } else {
                throw $this->dispatchConnectionException($connectionException, $errorMessage);
            }
        }

        return $this->decodeJson($response->getBody()->getContents());
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
        try {
            return Tools::decodeJSON($contents, true);
        } catch (\JsonException $exception) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to decode response form Pure API', self::INVALID_RESPONSE_FORMAT_ERROR_ID);
        }
    }

    private function getConnection(): Connection
    {
        if ($this->connection === null) {
            $connection = new Connection($this->config->getPureApiUrl());
            $this->connection = $connection;
        }

        return $this->connection;
    }
}
