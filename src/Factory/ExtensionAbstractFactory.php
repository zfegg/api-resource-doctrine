<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Factory;

use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionParameter;

class ExtensionAbstractFactory implements AbstractFactoryInterface
{
    protected array $aliases = [];

    /**
     * @param string[] $aliases
     */
    public function __construct(array $aliases = [])
    {
        $this->aliases = $aliases;
    }

    /**
     * @inheritdoc
     */
    public function canCreate(ContainerInterface $container, $requestedName): bool
    {
        return class_exists($requestedName);
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): mixed
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

    private function resolveParameterWithConfigService(
        ContainerInterface $container,
        string $requestedName,
        array $options
    ): callable {
        /**
         * @param ReflectionParameter $parameter
         * @return mixed
         * @throws ServiceNotFoundException If type-hinted parameter cannot be
         *   resolved to a service in the container.
         */
        return function (ReflectionParameter $parameter) use ($container, $requestedName, $options) {
            $type = $parameter->getType() ? $parameter->getType()->getName() : null;
            $name = $parameter->getName();

            if (isset($options[$name]) && in_array($type, ['string', 'int', 'bool', 'float', 'array', 'mixed', null])) {
                return $options[$name];
            }

            return $this->resolveParameter($parameter, $container, $requestedName, $options);
        };
    }

    /**
     * Logic common to all parameter resolution.
     *
     * @return mixed
     * @throws ServiceNotFoundException If type-hinted parameter cannot be
     *   resolved to a service in the container.
     */
    private function resolveParameter(
        ReflectionParameter $parameter,
        ContainerInterface $container,
        string $requestedName,
        array $options
    ) {
        if ((! $parameter->getType()) || $parameter->getType()->isBuiltin()) {
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

        $type = $parameter->getType()->getName();
        $type = isset($this->aliases[$type]) ? $this->aliases[$type] : $type;

        if (isset($options[$parameter->getName()])
            && is_string($options[$parameter->getName()])
            && $container->has($options[$parameter->getName()])
        ) {
            return $container->get($options[$parameter->getName()]);
        }

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
