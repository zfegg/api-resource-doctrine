<?php


namespace Zfegg\ApiResourceDoctrine;

class ConfigProvider
{

    public function __invoke()
    {
        return [
            'dependencies'       => [
                'abstract_factories' => [
                    Factory\DbalResourceAbstractFactory::class,
                    Factory\OrmResourceAbstractFactory::class,
                ],
                'factories' => [
                    Extension\ExtensionsFactory::class => Factory\ExtensionsFactoryFactory::class,
                ]
            ],
        ];
    }
}
