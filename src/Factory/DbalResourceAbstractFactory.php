<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Factory;

use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;
use Zfegg\ApiResourceDoctrine\Dbal\DbalResource;
use Zfegg\ApiResourceDoctrine\Extension\ExtensionsFactory;

class DbalResourceAbstractFactory implements AbstractFactoryInterface
{

    /**
     * @inheritdoc
     */
    public function canCreate(ContainerInterface $container, $requestedName): bool
    {
        return isset($container->get('config')['doctrine.dbal-resources'][$requestedName]);
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): mixed
    {
        $config = $container->get('config')['doctrine.dbal-resources'][$requestedName];
        $extensions = $container->get(ExtensionsFactory::class)->create($config['extensions'] ?? []);

        return new DbalResource(
            $container->get($config['connection'] ?? 'doctrine.connection.default'),
            $config['table'],
            $config['primary'] ?? 'id',
            $extensions,
            isset($config['parent']) ? $container->get($config['parent']) : null,
            $config['parent_context_key'] ?? null,
            $config['types'] ?? [],
        );
    }
}
