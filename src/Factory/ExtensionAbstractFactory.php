<?php

namespace Zfegg\ApiResourceDoctrine\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use ReflectionClass;
use ReflectionParameter;

class ExtensionAbstractFactory implements AbstractFactoryInterface
{
    protected $aliases = [];

    /**
     * @param string[] $aliases
     */
    public function __construct(array $aliases = [])
    {
        if (! empty($aliases)) {
            $this->aliases = $aliases;
        }
    }

    public function canCreate(ContainerInterface $container, $requestedName)
    {
        return class_exists($requestedName);
    }

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $reflectionClass = new ReflectionClass($requestedName);

        if (null === ($constructor = $reflectionClass->getConstructor())) {
            return new $requestedName();
        }

        $reflectionParameters = $constructor->getParameters();

        if (empty($reflectionParameters)) {
            return new $requestedName();
        }

        $resolver = $this->resolveParameterWithConfigService($container, $requestedName, $options ?? []);

        $parameters = array_map($resolver, $reflectionParameters);

        return new $requestedName(...$parameters);
    }

    /**
     * Returns a callback for resolving a parameter to a value, including mapping 'config' arguments.
     *
     * Unlike resolveParameter(), this version will detect `$config` array
     * arguments and have them return the 'config' service.
     *
     * @param ContainerInterface $container
     * @param string $requestedName
     * @return callable
     */
    private function resolveParameterWithConfigService(ContainerInterface $container, string $requestedName, array $options)
    {
        /**
         * @param ReflectionParameter $parameter
         * @return mixed
         * @throws ServiceNotFoundException If type-hinted parameter cannot be
         *   resolved to a service in the container.
         */
        return function (ReflectionParameter $parameter) use ($container, $requestedName, $options) {
            $type =  $parameter->getType() ? $parameter->getType()->getName() : null;
            $name = $parameter->getName();

            if (isset($options[$name]) && in_array($type, ['string', 'int', 'bool', 'float', 'array', null])) {
                return $options[$name];
            }

            return $this->resolveParameter($parameter, $container, $requestedName);
        };
    }

    /**
     * Logic common to all parameter resolution.
     *
     * @param ReflectionParameter $parameter
     * @param ContainerInterface $container
     * @param string $requestedName
     * @return mixed
     * @throws ServiceNotFoundException If type-hinted parameter cannot be
     *   resolved to a service in the container.
     */
    private function resolveParameter(ReflectionParameter $parameter, ContainerInterface $container, $requestedName)
    {
        if (! $parameter->getClass()) {
            if (! $parameter->isDefaultValueAvailable()) {
                throw new ServiceNotFoundException(sprintf(
                    'Unable to create service "%s"; unable to resolve parameter "%s" '
                    . 'to a class, interface, or array type',
                    $requestedName,
                    $parameter->getName()
                ));
            }

            return $parameter->getDefaultValue();
        }

        $type = $parameter->getClass()->getName();
        $type = isset($this->aliases[$type]) ? $this->aliases[$type] : $type;

        if ($container->has($type)) {
            return $container->get($type);
        }

        if (! $parameter->isOptional()) {
            throw new ServiceNotFoundException(sprintf(
                'Unable to create service "%s"; unable to resolve parameter "%s" using type hint "%s"',
                $requestedName,
                $parameter->getName(),
                $type
            ));
        }

        // Type not available in container, but the value is optional and has a
        // default defined.
        return $parameter->getDefaultValue();
    }
}