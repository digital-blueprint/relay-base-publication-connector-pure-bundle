<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\DependencyInjection;

use Dbp\Relay\BasePublicationConnectorPureBundle\Service\PublicationService;
use Dbp\Relay\CoreBundle\Extension\ExtensionTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayBasePublicationConnectorPureExtension extends ConfigurableExtension
{
    use ExtensionTrait;

    public function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $definition = $container->getDefinition(PublicationService::class);
        $definition->addMethodCall('setConfig', [$mergedConfig]);
    }
}
