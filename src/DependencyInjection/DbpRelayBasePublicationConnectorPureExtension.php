<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\DependencyInjection;

use Dbp\Relay\BasePublicationConnectorPureBundle\EventSubscriber\PublicationLocalDataEventSubscriber;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayBasePublicationConnectorPureExtension extends ConfigurableExtension
{
    public function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $definition = $container->getDefinition('Dbp\Relay\BasePublicationConnectorPureBundle\Service\Config');
        $definition->addMethodCall('setConfig', [$mergedConfig]);

        /*$container->getDefinition(PublicationLocalDataEventSubscriber::class)
            ->addMethodCall('setConfig', [$mergedConfig]);*/

        $postEventSubscriber = $container->getDefinition(PublicationLocalDataEventSubscriber::class);
        $postEventSubscriber->addMethodCall('setConfig', [$mergedConfig]);
    }
}