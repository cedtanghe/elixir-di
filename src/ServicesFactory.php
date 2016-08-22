<?php

namespace Elixir\DI;

use Elixir\DI\ContainerInterface;
use Elixir\DI\ContainerResolvableInterface;
use Elixir\DI\ProviderInterface;
use Elixir\Dispatcher\DispatcherInterface;
use Elixir\Dispatcher\SubscriberInterface;

/**
 * @author Cédric Tanghe <ced.tanghe@gmail.com>
 */
class ServicesFactory
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
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param string|array $config
     */
    public function load($config)
    {
        if (!is_array($config))
        {
            $config = include $config;
        }
        
        $reservedKeywords = [
            'providers',
            'bindings',
            'shared',
            'converters',
            'tags',
            'aliases',
            'extenders',
            'initializers',
            'subscribers'
        ];
        
        $bindServices = true;
        
        foreach (array_keys($config) as $key)
        {
            if (in_array($key, $reservedKeywords))
            {
                $bindServices = false;
                break;
            }
        }
        
        // Providers
        if (isset($config['providers']))
        {
            foreach ($config['providers'] as $provider)
            {
                if (!($provider instanceof ProviderInterface))
                {
                    if (is_string($provider))
                    {
                        $provider = new $provider();
                    }
                    else
                    {
                        $provider = call_user_func($provider);
                    }
                }
                
                $this->container->addProvider($provider);
            }
        }
        
        // Bind
        if ($bindServices || isset($config['bindings']))
        {
            $services = isset($config['bindings']) ? $config['bindings'] : $config;
            
            foreach ($services as $key => $data)
            {
                $value = isset($data['value']) ? $data['value'] : $data;
                $options = isset($data['options']) ? $data['options'] : [];
                
                $this->container->bind($key, $value, $options);
            }
        }
        
        // Share
        if (isset($config['shared']))
        {
            foreach ($config['shared'] as $key => $data)
            {
                $value = isset($data['value']) ? $data['value'] : $data;
                $options = isset($data['options']) ? $data['options'] : [];
                
                $this->container->share($key, $value, $options);
            }
        }
        
        // Converters
        if (isset($config['converters']) && ($this->container instanceof ContainerResolvableInterface))
        {
            foreach ($config['converters'] as $converter)
            {
                $this->container->addConverter($converter);
            }
        }
        
        // Tags
        if (isset($config['tags']))
        {
            foreach ($config['tags'] as $key => $tags)
            {
                foreach ((array)$tags as $tag)
                {
                    $this->container->addTag($key, $tag);
                }
            }
        }
        
        // Aliases
        if (isset($config['aliases']))
        {
            foreach ($config['aliases'] as $key => $aliases)
            {
                foreach ((array)$aliases as $alias)
                {
                    $this->container->addAlias($key, $alias);
                }
            }
        }
        
        // Extenders
        if (isset($config['extenders']))
        {
            foreach ($config['extenders'] as $key => $extenders)
            {
                foreach ((array)$extenders as $extender)
                {
                    $this->container->extend($key, $extender);
                }
            }
        }
        
        // Initializers
        if (isset($config['initializers']))
        {
            foreach ($config['initializers'] as $initializer)
            {
                $this->container->addInitializer($initializer);
            }
        }
        
        // Subscribers
        if (isset($config['subscribers']) && ($this->container instanceof DispatcherInterface))
        {
            foreach ($config['subscribers'] as $subscriber)
            {
                if (!($subscriber instanceof SubscriberInterface))
                {
                    if (is_string($subscriber))
                    {
                        $subscriber = new $subscriber();
                    }
                    else
                    {
                        $subscriber = call_user_func($subscriber);
                    }
                }
                
                $this->container->addSubscriber($subscriber);
            }
        }
    }
}
