<?php

namespace Elixir\DI;

/**
 * @author Cédric Tanghe <ced.tanghe@gmail.com>
 */
interface ContainerResolvableInterface extends ContainerInterface
{
    /**
     * @param callable $converter
     */
    public function addConverter(callable $converter);

    /**
     * @param string $id
     *
     * @return string
     */
    public function convert($id);

    /**
     * @param string $when
     * @param string $needs
     * @param mixed  $implementation
     */
    public function addContextualBinding($when, $needs, $implementation);

    /**
     * @param callable|string $callback
     * @param array           $options
     *
     * @return array
     */
    public function resolve($callback, array $options = []);

    /**
     * @param string $callback
     * @param array  $options
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    public function resolveCallable($callback, array $options = []);

    /**
     * @param string $class
     * @param array  $options
     *
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public function resolveClass($class, array $options = []);
}
