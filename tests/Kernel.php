<?php

declare(strict_types=1);

namespace Dbp\Relay\BasePublicationConnectorPureBundle\Tests;

use Dbp\Relay\BasePublicationBundle\DbpRelayBasePublicationBundle;
use Dbp\Relay\BasePublicationConnectorPureBundle\DbpRelayBasePublicationConnectorPureBundle;
use Dbp\Relay\CoreBundle\TestUtils\CoreTestKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use CoreTestKernelTrait;

    protected function registerAdditionalBundles(): iterable
    {
        yield new DbpRelayBasePublicationBundle();
        yield new DbpRelayBasePublicationConnectorPureBundle();
    }

    protected function configureAdditionalContainer(ContainerConfigurator $container): void
    {
        $container->extension('dbp_relay_base_publication_connector_pure', [
            'pure' => [
                'api_url' => 'https://pure.test.api/',
                'api_key' => 'test_api_key',
            ],
        ]);
    }
}
