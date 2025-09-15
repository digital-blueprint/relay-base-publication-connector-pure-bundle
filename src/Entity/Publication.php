<?php

declare(strict_types=1);

namespace Dbp\Relay\RelayBasePublicationConnectorPureBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use Dbp\Relay\RelayBasePublicationConnectorPureBundle\Rest\PublicationProcessor;
use Dbp\Relay\RelayBasePublicationConnectorPureBundle\Rest\PublicationProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'RelayBasePublicationConnectorPurePublication',
    types: ['https://schema.org/Publication'],
    operations: [
        new Get(
            uriTemplate: '/relay-base-publication-connector-pure/publications/{identifier}',
            openapi: new Operation(
                tags: ['Base-Publication Connector Pure Bundle'],
            ),
            provider: PublicationProvider::class
        ),
        new GetCollection(
            uriTemplate: '/relay-base-publication-connector-pure/publications',
            openapi: new Operation(
                tags: ['Base-Publication Connector Pure Bundle'],
            ),
            provider: PublicationProvider::class
        ),
        new Post(
            uriTemplate: '/relay-base-publication-connector-pure/publications',
            openapi: new Operation(
                tags: ['Base-Publication Connector Pure Bundle'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/ld+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                ],
                                'required' => ['name'],
                            ],
                            'example' => [
                                'name' => 'Example Name',
                            ],
                        ],
                    ])
                )
            ),
            processor: PublicationProcessor::class
        ),
        new Delete(
            uriTemplate: '/relay-base-publication-connector-pure/publications/{identifier}',
            openapi: new Operation(
                tags: ['Base-Publication Connector Pure Bundle'],
            ),
            provider: PublicationProvider::class,
            processor: PublicationProcessor::class
        ),
    ],
    normalizationContext: ['groups' => ['RelayBasePublicationConnectorPurePublication:output']],
    denormalizationContext: ['groups' => ['RelayBasePublicationConnectorPurePublication:input']]
)]
class Publication
{
    #[ApiProperty(identifier: true)]
    #[Groups(['RelayBasePublicationConnectorPurePublication:output'])]
    private ?string $identifier = null;

    #[ApiProperty(iris: ['https://schema.org/name'])]
    #[Groups(['RelayBasePublicationConnectorPurePublication:output', 'RelayBasePublicationConnectorPurePublication:input'])]
    private ?string $name;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
