<?php

namespace Elixir\DI;

/**
 * @author Cédric Tanghe <ced.tanghe@gmail.com>
 */
interface ContainerAwareInterface
{
    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null);
}
