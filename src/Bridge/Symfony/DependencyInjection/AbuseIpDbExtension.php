<?php

declare(strict_types=1);

namespace AbuseIpDb\Bridge\Symfony\DependencyInjection;

use AbuseIpDb\AbuseIpDbClient;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class AbuseIpDbExtension extends Extension
{
    /**
     * @param array<array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__) . '/Resources/config'));
        $loader->load('services.php');

        $options = [
            'base_uri' => $config['base_uri'],
            'default_max_age_in_days' => $config['default_max_age_in_days'],
        ];
        if (null !== $config['logger']) {
            $options['logger'] = new Reference($config['logger']);
        }

        $container->getDefinition(AbuseIpDbClient::class)
            ->replaceArgument(0, $config['api_key'])
            ->replaceArgument(1, null !== $config['http_client'] ? new Reference($config['http_client']) : null)
            ->replaceArgument(4, $options);

        // Exposed for consumers who need the raw key directly (e.g. via `bind:` or
        // the `#[Autowire]` attribute). Symfony cannot autowire a bare scalar
        // type-hint by type alone, so this parameter must be wired explicitly.
        $container->setParameter('abuse_ip_db.api_key', $config['api_key']);
    }

    public function getAlias(): string
    {
        return 'abuse_ip_db';
    }
}
