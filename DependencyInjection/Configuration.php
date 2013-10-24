<?php

namespace Ruudk\Payment\MultisafepayBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ruudk_payment_multisafepay');

        $methods = array(
            'ideal', 'mister_cash', 'giropay',
            'direct_ebanking', 'visa', 'mastercard',
            'maestro', 'bank_transfer', 'direct_debit'
        );

        $rootNode
            ->children()
                ->scalarNode('account_id')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('site_id')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('site_code')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('test')
                    ->defaultTrue()
                ->end()
                ->scalarNode('report_url')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
            ->end()

            ->fixXmlConfig('method')
            ->children()
                ->arrayNode('methods')
                    ->defaultValue($methods)
                    ->prototype('scalar')
                        ->validate()
                            ->ifNotInArray($methods)
                            ->thenInvalid('%s is not a valid method.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
