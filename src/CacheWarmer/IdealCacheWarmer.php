<?php

namespace Ruudk\Payment\AdyenBundle\CacheWarmer;

use JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException;
use Ruudk\Payment\AdyenBundle\Adyen\Api;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;

class IdealCacheWarmer extends CacheWarmer
{
    /**
     * @var \Ruudk\Payment\AdyenBundle\Adyen\Api
     */
    protected $api;

    /**
     * @param \Ruudk\Payment\AdyenBundle\Adyen\Api $api
     */
    public function __construct(Api $api)
    {
        $this->api = $api;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        try {
            $banks = $this->api->getBankList();
            $this->writeCacheFile($cacheDir . '/ruudk_payment_adyen_ideal.php', sprintf('<?php return %s;', var_export($banks, true)));
        } catch(CommunicationException $exception) {
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