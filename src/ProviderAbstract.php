<?php

namespace Elixir\DI;

use Elixir\DI\ProviderInterface;

/**
 * @author Cédric Tanghe <ced.tanghe@gmail.com>
 */
abstract class ProviderAbstract implements ProviderInterface 
{
    /**
     * @var boolean
     */
    protected $deferred = false;
    
    /**
     * @var array
     */
    protected $provides = [];
    
    /**
     * {@inheritdoc}
     */
    public function isDeferred()
    {
        return $this->deferred;
    }
    
    /**
     * {@inheritdoc}
     */
    public function provided($service)
    {
        return in_array($service, $this->provides());
    }
    
    /**
     * {@inheritdoc}
     */
    public function provides()
    {
        return $this->provides;
    }
}
