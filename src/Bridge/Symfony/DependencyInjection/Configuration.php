<?php

declare(strict_types=1);

namespace AbuseIpDb\Bridge\Symfony\DependencyInjection;

use AbuseIpDb\Http\HttpTransport;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('abuse_ip_db');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('api_key')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('base_uri')->defaultValue(HttpTransport::DEFAULT_BASE_URI)->end()
                ->integerNode('default_max_age_in_days')->defaultNull()->min(1)->max(365)->end()
                ->scalarNode('http_client')->defaultNull()->info('Service id of a PSR-18 client; auto-discovered when null.')->end()
                ->scalarNode('logger')->defaultNull()->info('Service id of a PSR-3 logger.')->end()
            ->end();

        return $treeBuilder;
    }
}
