<?php

namespace Mroca\RequestLogBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('mroca_request_log');

        $rootNode
            ->children()
                ->scalarNode('mocks_dir')->cannotBeEmpty()->info('The generated log files path')->defaultValue('%kernel.logs_dir%/mocks/')->end()
                ->booleanNode('hash_query_params')->info('Transform query params string into hash in the file names')->defaultFalse()->end()
                ->booleanNode('use_indexed_associative_array')->info('Use indexed foo[0]=bar format instead of foo[]=bar')->defaultFalse()->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
