<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Entity;

class Publication
{
    private ?string $identifier = null;
    private ?string $uuid = null;
    private ?string $title = null;
    private ?string $abstract = null;
    private ?string $doi = null;
    private ?string $publicationDate = null;
    private ?string $publicationType = null;
    private ?string $journal = null;
    private ?string $volume = null;
    private ?string $issue = null;
    private ?string $pages = null;
    private array $authors = [];
    private array $keywords = [];
    private ?string $url = null;

    // Getters and setters
    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getAbstract(): ?string
    {
        return $this->abstract;
    }

    public function setAbstract(?string $abstract): void
    {
        $this->abstract = $abstract;
    }

    public function getDoi(): ?string
    {
        return $this->doi;
    }

    public function setDoi(?string $doi): void
    {
        $this->doi = $doi;
    }

    public function getPublicationDate(): ?string
    {
        return $this->publicationDate;
    }

    public function setPublicationDate(?string $publicationDate): void
    {
        $this->publicationDate = $publicationDate;
    }

    public function getPublicationType(): ?string
    {
        return $this->publicationType;
    }

    public function setPublicationType(?string $publicationType): void
    {
        $this->publicationType = $publicationType;
    }

    public function getJournal(): ?string
    {
        return $this->journal;
    }

    public function setJournal(?string $journal): void
    {
        $this->journal = $journal;
    }

    public function getVolume(): ?string
    {
        return $this->volume;
    }

    public function setVolume(?string $volume): void
    {
        $this->volume = $volume;
    }

    public function getIssue(): ?string
    {
        return $this->issue;
    }

    public function setIssue(?string $issue): void
    {
        $this->issue = $issue;
    }

    public function getPages(): ?string
    {
        return $this->pages;
    }

    public function setPages(?string $pages): void
    {
        $this->pages = $pages;
    }

    public function getAuthors(): array
    {
        return $this->authors;
    }

    public function setAuthors(array $authors): void
    {
        $this->authors = $authors;
    }

    // Added this method to get formatted author string for the base publication entity
    public function getAuthorsFormatted(): string
    {
        if (empty($this->authors)) {
            return '';
        }

        $authorNames = array_map(function ($author) {
            $firstName = $author['firstName'] ?? '';
            $lastName = $author['lastName'] ?? '';

            return trim($firstName.' '.$lastName);
        }, $this->authors);

        return implode(', ', $authorNames);
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function setKeywords(array $keywords): void
    {
        $this->keywords = $keywords;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }
}
