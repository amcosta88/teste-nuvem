<?php

namespace TiendaNube\Checkout\Service\Store;

use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use TiendaNube\Checkout\Model\Store;

class StoreService implements StoreServiceInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(RequestInterface $request, LoggerInterface $logger)
    {
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * Get the current store instance
     *
     * @return Store
     */
    public function getCurrentStore(): Store
    {
        $token = $this->request->getHeaderLine('Authentication-Bearer');

        // @TODO build logic to retrieve store from database and delete the code bellow
        if ('YouShallPass' === $token) {
            $store = new Store();
            $store->enableBetaTesting();

            return $store;
        }

        if ('BetaTester' === $token) {
            $store = new Store();
            $store->enableBetaTesting();

            return $store;
        }

        if ('NotBetaTester' === $token) {
            $store = new Store();
            $store->disableBetaTesting();

            return $store;
        }

        $message = sprintf('No store was found by token: "%s"', $token);
        $this->logger->debug($message);
        throw new \InvalidArgumentException($message);
    }

}