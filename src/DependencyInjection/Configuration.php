<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\DependencyInjection;

use Dbp\Relay\BasePublicationConnectorPureBundle\EventSubscriber\PublicationLocalDataEventSubscriber;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_base_publication_connector_pure');

        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->arrayNode('pure')
            ->isRequired()
            ->children()
            ->scalarNode('api_url')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('The base URL of the Pure API instance')
            ->example('https://pure-test.tugraz.at/')
            ->end()
            ->scalarNode('api_key')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('The Pure API key')
            ->end()
            ->end()
            ->end()
            ->end();

        // local data mapping configuration
        $rootNode->append(PublicationLocalDataEventSubscriber::getLocalDataMappingConfigNodeDefinition());

        return $treeBuilder;
    }
}