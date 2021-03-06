<?php

namespace Elixir\DI;

use Elixir\Dispatcher\DispatcherInterface;
use Elixir\Dispatcher\DispatcherTrait;

/**
 * @author Cédric Tanghe <ced.tanghe@gmail.com>
 */
class Container implements ContainerResolvableInterface, DispatcherInterface, \ArrayAccess
{
    use DispatcherTrait;

    /**
     * @var array
     */
    protected $bindings = [];

    /**
     * @var array
     */
    protected $instances = [];

    /**
     * @var array
     */
    protected $resolved = [];

    /**
     * @var array
     */
    protected $tags = [];

    /**
     * @var array
     */
    protected $aliases = [];

    /**
     * @var array
     */
    protected $extenders = [];

    /**
     * @var array
     */
    protected $initializers = [];

    /**
     * @var array
     */
    protected $contextual = [];

    /**
     * @var array
     */
    protected $converters = [];

    /**
     * @var bool
     */
    protected $disableConverter = false;

    /**
     * @var array
     */
    protected $resolvedStack = [];

    /**
     * @var array
     */
    protected $providers = [];

    /**
     * {@inheritdoc}
     */
    public function addProvider(ProviderInterface $provider)
    {
        if (!$pProvider->isDeferred()) {
            $pProvider->register($this);
        } else {
            $this->providers[] = $provider;
        }
    }

    /**
     * @return array
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        if (isset($this->aliases[$key])) {
            $key = $this->aliases[$key];
        }

        if (isset($this->bindings[$key])) {
            return true;
        }

        $i = count($this->providers);

        while ($i--) {
            $provider = $this->providers[$i];

            if ($provider->provided($key)) {
                $provider->register($this);
                array_splice($this->providers, $i, 1);

                return $this->has($key);
            }
        }

        if (!$this->disableConverter) {
            $this->disableConverter = true;

            foreach ($this->converters as $converter) {
                $converted = call_user_func_array($converter, [$key, $this]);

                if ($converted !== $key && $this->has($converted)) {
                    $this->addAlias($key, $converted);

                    return true;
                }
            }

            $this->disableConverter = false;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, array $options = [], $default = null)
    {
        $options += [
            'throw' => false,
            'rebuild' => false,
            'rebuild_all' => false,
            'resolve' => false,
        ];

        if ($options['rebuild_all']) {
            $options['rebuild'] = true;
        }

        $create = true;

        if ($this->has($key)) {
            if (isset($this->aliases[$key])) {
                $key = $this->aliases[$key];
            }

            if (array_key_exists($key, $this->instances) && !$options['rebuild']) {
                $value = $this->instances[$key];
                $create = false;
            } else {
                $arguments = [$this];

                if (isset($options['arguments']) && count($options['arguments']) > 0) {
                    $arguments = array_merge($arguments, $options['arguments']);
                }

                if (is_callable($this->bindings[$key]['value'])) {
                    $value = call_user_func_array($this->bindings[$key]['value'], $arguments);
                } else {
                    try {
                        if (!$options['rebuild_all']) {
                            $options['rebuild'] = false;
                        }

                        $value = $this->resolveClass($key, $options);
                    } catch (\RuntimeException $e) {
                        if ($options['throw']) {
                            throw $e;
                        } else {
                            return is_callable($default) ? call_user_func($default) : $default;
                        }
                    }
                }
            }
        } else {
            if (!$options['resolve']) {
                return is_callable($default) ? call_user_func($default) : $default;
            }

            try {
                if (!$options['rebuild_all']) {
                    $options['rebuild'] = false;
                }

                $value = $this->resolveClass($key, $options);
            } catch (\RuntimeException $e) {
                if ($options['throw']) {
                    throw $e;
                } else {
                    return is_callable($default) ? call_user_func($default) : $default;
                }
            }
        }

        if (isset($this->extenders[$key])) {
            foreach ($this->extenders[$key] as $extender) {
                $value = call_user_func_array($extender, [$value, $this]);
            }
        }

        if ($create && count($this->initializers) > 0) {
            foreach ($this->initializers as $initializer) {
                $value = call_user_func_array($initializer, [$value, $this]);
            }
        }

        $event = new ContainerEvent(
            ContainerEvent::RESOLVED,
            [
                'service' => $key,
                'resolved_service' => $value,
                'initialized' => $create,
            ]
        );

        $this->dispatch($event);
        $value = $event->getResolvedObject();

        $this->resolved[$key] = true;

        if ($this->bindings[$key]['shared']) {
            unset($this->extenders[$key]);
            $this->instances[$key] = $value;
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function addConverter(callable $converter)
    {
        $this->converters[] = $converter;
    }

    /**
     * @return array
     */
    public function getConverters()
    {
        return $this->converters;
    }

    /**
     * {@inheritdoc}
     */
    public function convert($id)
    {
        foreach ($this->converters as $converter) {
            $id = call_user_func_array($converter, [$id, $this]);
        }

        return $id;
    }

    /**
     * {@inheritdoc}
     */
    public function addContextualBinding($when, $needs, $implementation)
    {
        $this->contextual[$when][$needs] = $implementation;
    }

    /**
     * @return array
     */
    public function getContextualBindings()
    {
        return $this->contextual;
    }

    /**
     * @param string $needs
     *
     * @return mixed
     */
    protected function getContextualObject($needs)
    {
        $when = end($this->resolvedStack);

        if (isset($this->contextual[$when][$needs])) {
            return $this->contextual[$when][$needs];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve($callback, array $options = [])
    {
        $options += [
            'resolve' => true,
            'throw' => true,
        ];

        if (!is_callable($callback) && false === strpos($callback, '::')) {
            return $this->get($callback, $options);
        } else {
            return $this->resolveCallable($callback, $options);
        }
    }

    /**
     * @param string $callback
     * @param array  $options
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    public function resolveCallable($callback, array $options = [])
    {
        $options += [
            'resolve' => true,
            'throw' => true,
        ];

        if (is_string($callback) && false !== strpos($callback, '::')) {
            $callback = explode('::', $callback);
        }

        if (is_array($callback)) {
            $reflector = new ReflectionMethod($callback[0], $callback[1]);
        } else {
            $reflector = new ReflectionFunction($callback);
        }

        return [
            $callback,
            $this->getDependencies($reflector->getParameters(), $options),
        ];
    }

    /**
     * @param string $class
     * @param array  $options
     *
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public function resolveClass($class, array $options = [])
    {
        $options += [
            'resolve' => true,
            'throw' => true,
        ];

        $contextual = $this->getContextualObject($class);

        if (null !== $contextual) {
            if (is_callable($contextual)) {
                return call_user_func_array($contextual, [$this]);
            }

            $class = $contextual;
        }

        $reflector = new \ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException(sprintf('Class %s is not instanciable.', $class));
        }

        $constructor = $reflector->getConstructor();

        if (null === $constructor) {
            return new $class();
        }

        $this->resolvedStack[] = $class;
        $arguments = $this->getDependencies($constructor->getParameters(), $options);
        array_pop($this->resolvedStack);

        return $reflector->newInstanceArgs($arguments);
    }

    /**
     * @param array $dependencies
     * @param array $options
     *
     * @return array
     *
     * @throws \RuntimeException
     * @throws \Exception
     */
    protected function getDependencies(array $dependencies, array $options = [])
    {
        $arguments = [];
        $available = isset($options['resolver_arguments_available']) ? $options['resolver_arguments_available'] : [];

        foreach ($dependencies as $dependency) {
            $name = $dependency->getName();

            if (array_key_exists($name, $available)) {
                $arguments[] = $available[$name];
            } else {
                $class = $dependency->getClass();

                if (null === $class) {
                    if ($dependency->isDefaultValueAvailable()) {
                        $arguments[] = $dependency->getDefaultValue();
                    } else {
                        throw  new \RuntimeException(sprintf('No default value available for parameter %s.', $name));
                    }
                } else {
                    try {
                        $o = ['throw' => true] + $options;
                        $arguments[] = $this->get($class->name, $o);
                    } catch (\Exception $e) {
                        if ($dependency->isOptional()) {
                            $arguments[] = $dependency->getDefaultValue();
                        } else {
                            throw $e;
                        }
                    }
                }
            }
        }

        return $arguments;
    }

    /**
     * {@inheritdoc}
     */
    public function bind($key, $value, array $options = [])
    {
        $options += [
            'shared' => false,
            'tags' => [],
            'aliases' => [],
            'extenders' => [],
        ];

        if (isset($this->aliases[$key])) {
            $key = $this->aliases[$key];
        }

        $this->bindings[$key] = [
            'shared' => $options['shared'],
            'value' => $value,
        ];

        unset($this->instances[$key]);
        unset($this->resolved[$key]);

        foreach ($options['extenders'] as $extender) {
            $this->extend($key, $extender);
        }

        $this->dispatch(new ContainerEvent(ContainerEvent::BINDED, ['service' => $key]));

        foreach ($options['tags'] as $tag) {
            $this->addTag($key, $tag);
        }

        foreach ($options['aliases'] as $alias) {
            $this->addAlias($key, $alias);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function share($key, $value, array $options = [])
    {
        $options['shared'] = true;
        $this->bind($key, $value, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function instance($key, $value, array $options = [])
    {
        $this->share($key, $this->wrap($value), $options);

        $this->resolved[$key] = true;
        $this->instances[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function unbind($key)
    {
        if (isset($this->aliases[$key])) {
            $key = $this->aliases[$key];
        }

        foreach ($this->aliases as $alias => $k) {
            if ($key == $k) {
                unset($this->aliases[$alias]);
            }
        }

        foreach ($this->tags as $tag => &$ks) {
            $i = count($ks);

            while ($i--) {
                if ($ks[$i] == $key) {
                    array_splice($ks, $i, 1);
                    break;
                }
            }

            if (count($ks) == 0) {
                unset($this->tags[$tag]);
            }
        }

        unset($this->extenders[$key]);
        unset($this->bindings[$key]);
        unset($this->instances[$key]);
        unset($this->resolved[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function extend($key, callable $value)
    {
        if (isset($this->aliases[$key])) {
            $key = $this->aliases[$key];
        }

        $this->extenders[$key][] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function addInitializer(callable $value)
    {
        $this->initializers[] = $value;
    }

    /**
     * @return array
     */
    public function getInitializers()
    {
        return $this->initializers;
    }

    /**
     * @param mixed $value
     *
     * @return \Closure
     */
    public function wrap($value)
    {
        return function () use ($value) {
            return $value;
        };
    }

    /**
     * {@inheritdoc}
     */
    public function all(array $options = [])
    {
        $options += [
            'raw' => false,
            'providers' => false,
            'contextual_bindings' => false,
        ];

        $services = [];
        $data = [];

        if ($options['providers'] || $options['contextual_bindings']) {
            $data['services'] = &$services;

            if ($options['providers']) {
                $data['providers'] = $this->providers;
            }

            if ($options['contextual_bindings']) {
                $data['contextual_bindings'] = $this->contextual;
            }
        } else {
            $data = &$services;
        }

        $keys = array_keys($this->bindings);

        foreach ($keys as $key) {
            $services[$key] = $options['raw'] ? $this->raw($key) : $this->get($key, ['throw' => false]);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function replace(array $data)
    {
        $services = array_keys($data);

        foreach ($services as $service) {
            $this->unbind($service);
        }

        $this->bindings = [];
        $this->merge($data);
    }

    /**
     * {@inheritdoc}
     */
    public function addAlias($key, $alias)
    {
        if ($this->has($key)) {
            if (isset($this->aliases[$key])) {
                $key = $this->aliases[$key];
            }

            $this->aliases[$alias] = $key;
            $this->dispatch(new ContainerEvent(ContainerEvent::ALIASED, ['service' => $key, 'alias' => $alias]));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addTag($key, $tag)
    {
        if ($this->has($key)) {
            if (isset($this->aliases[$key])) {
                $key = $this->aliases[$key];
            }

            if (!isset($this->tags[$tag]) || !in_array($key, $this->tags[$tag])) {
                $this->tags[$tag][] = $key;
                $this->dispatch(new ContainerEvent(ContainerEvent::TAGGED, ['service' => $key, 'tag' => $tag]));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByTag($tag, array $options = [])
    {
        foreach ($this->providers as $providers) {
            $providers->register($this);
        }

        $services = [];
        $options += ['throw' => false];

        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $key) {
                $services[$key] = $this->get($key, $options);
            }
        }

        return $services;
    }

    /**
     * @param string $key
     *
     * @return array
     */
    public function raw($key)
    {
        if (!$this->has($key)) {
            return null;
        }

        if (isset($this->aliases[$key])) {
            $key = $this->aliases[$key];
        }

        $data = $this->bindings[$key];
        $data['resolved'] = $this->isResolved($key);

        if (array_key_exists($key, $this->instances)) {
            $data['instance'] = $this->instances[$key];
        }

        $data['extenders'] = isset($this->extenders[$key]) ? $this->extenders[$key] : [];
        $data['aliases'] = [];
        $data['tags'] = [];

        foreach ($this->aliases as $alias => $k) {
            if ($key == $k) {
                $data['aliases'][] = $alias;
            }
        }

        foreach ($this->tags as $tag => $ks) {
            if (in_array($key, $ks)) {
                $data['tags'][] = $tag;
            }
        }

        return $data;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function isShared($key)
    {
        if ($this->has($key)) {
            if (isset($this->aliases[$key])) {
                $key = $this->aliases[$key];
            }

            return $this->bindings[$key]['shared'];
        }

        return false;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function isTagged($key)
    {
        if ($this->has($key)) {
            if (isset($this->aliases[$key])) {
                $key = $this->aliases[$key];
            }

            foreach ($this->tags as $tag => $keys) {
                if (in_array($key, $keys)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function isAliased($key)
    {
        if ($this->has($key)) {
            if (isset($this->aliases[$key])) {
                $key = $this->aliases[$key];
            }

            foreach ($this->aliases as $alias => $k) {
                if ($key == $k) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function isResolved($key)
    {
        if (isset($this->aliases[$key])) {
            $key = $this->aliases[$key];
        }

        return isset($this->resolved[$key]) && $this->resolved[$key];
    }

    /**
     * @ignore
     */
    public function offsetExists($key)
    {
        return $this->has($key);
    }

    /**
     * @ignore
     */
    public function offsetSet($key, $value)
    {
        if (null === $key) {
            throw new \InvalidArgumentException('The key can not be undefined.');
        }

        $this->bind($key, $value);
    }

    /**
     * @ignore
     */
    public function offsetGet($key)
    {
        return $this->get($key);
    }

    /**
     * @ignore
     */
    public function offsetUnset($key)
    {
        $this->unbind($key);
    }

    /**
     * {@inheritdoc}
     */
    public function merge(ContainerInterface $container)
    {
        $data = $container->all([
            'raw' => true,
            'providers' => true,
            'contextual_bindings' => true,
        ]);

        foreach ($gets['services'] as $key => $config) {
            $this->bind($key, $config['value'], $config);

            if ($config['resolved'] && isset($config['instance'])) {
                $this->resolved[$key] = true;
                $this->instances[$key] = $config['instance'];
            }
        }

        foreach ($data['providers'] as $provider) {
            $this->addProvider($provider);
        }

        $this->contextual = array_merge($this->contextual, $data['contextual_bindings']);
    }

    /**
     * @ignore
     */
    public function __debugInfo()
    {
        $services = $this->all(['raw' => true]);
        $provides = [];
        $contextualBindings = [];

        foreach ($services as $key => &$data) {
            unset($data['value']);
        }

        foreach ($this->providers as $provider) {
            $provides = array_merge($provides, $provider->provides());
        }

        foreach ($this->contextual as $key => $value) {
            $contextualBindings[$key] = array_keys($value);
        }

        return [
            'services' => $services,
            'resolved' => array_keys($this->resolved),
            'aliases' => $this->aliases,
            'tags' => $this->tags,
            'provides' => $provides,
            'extenders' => array_keys($this->extenders),
            'initializers' => $this->initializers,
            'contextual_bindings' => $contextualBindings,
        ];
    }
}
