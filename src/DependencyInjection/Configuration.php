<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\DependencyInjection;

use Dbp\Relay\CoreBundle\Authorization\AuthorizationConfigDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    private function getAuthorizationConfigNode(): NodeDefinition
    {
        return AuthorizationConfigDefinition::create()
            ->addRole(self::ROLE_USER, 'false', 'Returns true if the user is allowed to use the API.')
            ->addRole(self::ROLE_ADMIN, 'false', 'Returns true if the user has unrestricted access to the API.')
            ->getNodeDefinition();
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_base_publication_connector_pure');
        $treeBuilder->getRootNode()
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
            ->append($this->getAuthorizationConfigNode())
            ->end();

        return $treeBuilder;
    }
}