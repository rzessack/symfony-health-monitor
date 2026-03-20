<?php

declare(strict_types=1);

namespace Rzessack\HealthMonitor\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('health_monitor');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('timezone')
                    ->defaultValue('UTC')
                    ->info('Timezone for snapshot timestamps and aggregation queries')
                ->end()
                ->integerNode('retention_days')
                    ->defaultValue(7)
                    ->min(1)
                    ->info('Number of days to keep snapshots before cleanup')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
