<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Zfegg\ApiResourceDoctrine\ORM\OrmResource;
use Zfegg\ApiResourceDoctrine\Extension\ExtensionsFactory;

class OrmResourceAbstractFactory implements AbstractFactoryInterface
{

    /**
     * @inheritdoc
     */
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        return isset($container->get('config')['doctrine.orm-resources'][$requestedName]);
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('config')['doctrine.orm-resources'][$requestedName];
        $extensions = $container->get(ExtensionsFactory::class)->create($config['extensions'] ?? []);

        return new OrmResource(
            $container->get($config['entity_manager'] ?? 'doctrine.entity_manager.default'),
            $config['entity'],
            $container->get($config['serializer'] ?? SerializerInterface::class),
            $container->has(PropertyAccessorInterface::class)
                ? $container->get(PropertyAccessorInterface::class)
                : null,
            $extensions,
            isset($config['parent']) ? $container->get($config['parent']) : null
        );
    }
}
