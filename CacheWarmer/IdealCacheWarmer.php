<?php

namespace Ruudk\Payment\MultisafepayBundle\CacheWarmer;

use Omnipay\MultiSafepay\Gateway;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;

class IdealCacheWarmer extends CacheWarmer
{
    /**
     * @var \Omnipay\MultiSafepay\Gateway
     */
    private $gateway;

    /**
     * @var string
     */
    private $environment;

    /**
     * @param Gateway $gateway
     * @param string  $environment
     */
    public function __construct(Gateway $gateway, $environment)
    {
        $this->gateway = $gateway;
        $this->environment = $environment;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        try {
            if('test' !== $this->environment) {
                $banks = $this->gateway->fetchIssuers()->send()->getIssuers();
            } else {
                $banks = array(3151 => 'Test bank');
            }

            $this->writeCacheFile($cacheDir . '/ruudk_payment_multisafepay_ideal.php', sprintf('<?php return %s;', var_export($banks, true)));
        } catch(\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * Optional warmers can be ignored on certain conditions.
     *
     * A warmer should return true if the cache can be
     * generated incrementally and on-demand.
     *
     * @return Boolean true if the warmer is optional, false otherwise
     */
    public function isOptional()
    {
        return false;
    }
}
