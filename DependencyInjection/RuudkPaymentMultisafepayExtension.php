<?php

namespace Ruudk\Payment\MultisafepayBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class RuudkPaymentMultisafepayExtension extends Extension
{
    /**
     * @param array            $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $container->setParameter('ruudk_payment_multisafepay.account_id', $config['account_id']);
        $container->setParameter('ruudk_payment_multisafepay.site_id', $config['site_id']);
        $container->setParameter('ruudk_payment_multisafepay.site_code', $config['site_code']);
        $container->setParameter('ruudk_payment_multisafepay.test', $config['test']);
        $container->setParameter('ruudk_payment_multisafepay.report_url', $config['report_url']);

        foreach($config['methods'] AS $method) {
            $this->addFormType($container, $method);
        }

        /**
         * When iDeal is not enabled, remove the cache warmer.
         */
        if(!in_array('ideal', $config['methods'])) {
            $container->removeDefinition('ruudk_payment_multisafepay.cache_warmer');
        }

        /**
         * When logging is disabled, remove logger and setLogger calls
         */
        if(false === $config['logger']) {
            $container->getDefinition('ruudk_payment_multisafepay.controller.notification')->removeMethodCall('setLogger');
            $container->getDefinition('ruudk_payment_multisafepay.plugin.default')->removeMethodCall('setLogger');
            $container->getDefinition('ruudk_payment_multisafepay.plugin.ideal')->removeMethodCall('setLogger');
            $container->removeDefinition('monolog.logger.ruudk_payment_multisafepay');
        }
    }

    protected function addFormType(ContainerBuilder $container, $method)
    {
        $fullMethod = 'multisafepay_' . $method;

        $definition = new Definition();
        $definition->setClass('%ruudk_payment_multisafepay.form.default_type.class%');
        $definition->addArgument($fullMethod);

        if($method === 'ideal') {
            $definition->setClass('%ruudk_payment_multisafepay.form.ideal_type.class%');
            $definition->addArgument('%kernel.cache_dir%');
        }

        $definition->addTag('payment.method_form_type');
        $definition->addTag('form.type', array(
            'alias' => $fullMethod
        ));

        $container->setDefinition(
            sprintf('ruudk_payment_multisafepay.form.%s_type', $method),
            $definition
        );
    }
}
