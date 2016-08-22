<?php

namespace Elixir\DI;

/**
 * @author Cédric Tanghe <ced.tanghe@gmail.com>
 */
interface ProviderInterface
{
    /**
     * @return bool
     */
    public function isDeferred();

    /**
     * @param ContainerInterface $container
     */
    public function register(ContainerInterface $container);

    /**
     * @param string $service
     *
     * @return bool
     */
    public function provided($service);

    /**
     * @return array
     */
    public function provides();
}
