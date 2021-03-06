<?php


namespace ZfeggTest\ApiResourceDoctrine;


use Doctrine\DBAL\Connection;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Zfegg\ApiResourceDoctrine\ConfigProvider;
use Zfegg\DoctrineHelper\Factory\DoctrineAbstractFactory;

trait SetUpContainerTrait
{
    private ServiceManager $container;

    protected function setUp(): void
    {
        $config = (new ConfigProvider())();
        $config = ArrayUtils::merge($config, $this->getConfig());
        $config = ArrayUtils::merge($config, $this->config);

        $this->container = new ServiceManager($config['dependencies']);
        $this->container->setService('config', new \ArrayObject($config));

        /** @var Connection $conn */
        $conn = $this->container->get('doctrine.connection.default');
        $userCreateSql = <<<EOT
create table users (
  id INTEGER
  constraint users_pk
  primary key autoincrement,
  name text
);
create unique index users_name_uindex on users (name);
EOT;
        $conn->prepare($userCreateSql)->execute();
    }

    public function getConfig()
    {
        return [
            'dependencies' => [
                'abstract_factories' => [
                    DoctrineAbstractFactory::class,
                ],
                'factories' => [
                    SerializerInterface::class => function () {
                        return new Serializer([
                            new ObjectNormalizer(),
                        ]);
                    }
                ]
            ],
            'doctrine' => [
                'configuration' => [
                    'default' => [
                        'driver' => 'default',
                    ],
                ],
                'connection' => [
                    'default' => [
                        'event_manager' => 'default',
                        'params' => [
                            'url' => 'sqlite:///:memory:',
//                            'url' => 'sqlite:///' . __DIR__ . '/test.db',
                        ],
                    ],
                ],
                'entity_manager' => [
                    'default' => [
                        'connection' => 'default',
                        'configuration' => 'default',
                    ],
                ],
                'driver' => [
                    'annotation' => [
                        'class' => \Doctrine\ORM\Mapping\Driver\AnnotationDriver::class,
                        'cache' => 'default',
                        'paths' => [
                            __DIR__ . '/Entity',
                        ],
                    ],
                    'default' => [
                        'class' => \Doctrine\Persistence\Mapping\Driver\MappingDriverChain::class,
                        'default_driver' => 'annotation',
                        'drivers' => [
                        ],
                    ],
                ],
                'cache' => [
                    'default' => [
                        'class' => \Doctrine\Common\Cache\ArrayCache::class,
                        'paths' => [],
                    ],
                ],
            ]
        ];
    }

}