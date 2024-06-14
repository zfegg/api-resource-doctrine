<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension;

use Laminas\ServiceManager\AbstractPluginManager;
use Laminas\ServiceManager\Exception\InvalidServiceException;

class ExtensionsFactory extends AbstractPluginManager
{

    /**
     * @inheritdoc
     */
    protected $instanceOf = ExtensionInterface::class;

    /**
     * @param array $config
     * @return ExtensionInterface[]
     */
    public function create(array $config): array
    {
        $extensions = [];
        foreach ($config as $name => $extensionConfig) {
            $extensions[] = $this->build($name, $extensionConfig);
        }

        return $extensions;
    }

    public function validate(mixed $instance): void
    {
        if ($instance instanceof $this->instanceOf) {
            return;
        }

        throw new InvalidServiceException(sprintf(
            'Plugin manager "%s" expected an instance of type "%s", but "%s" was received',
            static::class,
            $this->instanceOf,
            get_debug_type($instance)
        ));
    }
}
