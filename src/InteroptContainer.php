<?php

namespace Elixir\DI;

use Elixir\DI\ContainerInterface;
use Interop\Container\ContainerInterface as InteropContainerInterface;
use Interop\Container\Exception\NotFoundException;

/**
 * @author Cédric Tanghe <ced.tanghe@gmail.com>
 */
class InteroptContainer implements InteropContainerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;
    
    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
    
    /**
     * {@inheritdoc}
     */
    public function get($id)
    {
        if (!$this->has($id))
        {
            throw new NotFoundException('No entry was found for this identifier');
        }
        
        try
        {
            $entry = $this->get($id, ['throw' => true]);
        }
        catch (\Exception $exception)
        {
            throw new ContainerException ('Error while retrieving the entry');
        }
        
        return $entry;
    }
    
    /**
     * {@inheritdoc}
     */
    public function has($id)
    {
        return $this->container->has($id);
    }
    
    /**
     * @ignore
     */
    public function __call($method, $arguments) 
    {
        return call_user_func_array([$this->container, $method], $arguments);
    }
}
