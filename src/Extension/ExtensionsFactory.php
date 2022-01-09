<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension;

use Laminas\ServiceManager\AbstractPluginManager;

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
            $extensions[] = $this->get($name, $extensionConfig);
        }

        return $extensions;
    }
}
