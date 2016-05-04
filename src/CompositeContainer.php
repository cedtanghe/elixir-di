<?php

namespace Elixir\DI;

use Interop\Container\ContainerInterface as InteropContainerInterface;
use Interop\Container\Exception\NotFoundException;

/**
 * @author Cédric Tanghe <ced.tanghe@gmail.com>
 */
class CompositeContainer implements InteropContainerInterface
{
    /**
     * @var array
     */
    protected $containers = [];
    
    /**
     * @param array $containers
     */
    public function __construct(array $containers)
    {
        $this->containers = $containers;
    }
    
    /**
     * @param InteropContainerInterface $container
     * @return self
     */
    public function addContainer(InteropContainerInterface $container)
    {
        $this->containers[] = $container;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function get($id)
    {
        foreach ($this->containers as $container) 
        {
            if ($container->has($id)) 
            {
                return $container->get($id);
            }
        }
        
        throw new NotFoundException('No entry was found for this identifier');
    }
    
    /**
     * {@inheritdoc}
     */
    public function has($id)
    {
        foreach ($this->containers as $container) 
        {
            if ($container->has($id)) 
            {
                return true;
            }
        }
        
        return false;
    }
}
