<?php declare(strict_types = 1);

namespace ZfeggTest\ApiResourceDoctrine;

use Doctrine\Persistence\ManagerRegistry;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Psr\Container\ContainerInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Zfegg\ApiResourceDoctrine\ConfigProvider;
use Zfegg\ApiResourceDoctrine\Serializer\DoctrineEntityDenormalizer;
use Zfegg\ApiSerializerExt\Basic\CollectionNormalizer;
use Zfegg\DoctrineHelper\Factory\DoctrineAbstractFactory;

trait SetUpContainerTrait
{
    private ServiceManager $container;

    protected function setUp(): void
    {
        $config = (new ConfigProvider())();
        $config = ArrayUtils::merge($config, (new \Zfegg\DoctrineHelper\ConfigProvider())());
        $config = ArrayUtils::merge($config, $this->getConfig());
        $config = ArrayUtils::merge($config, $this->config);

        $this->container = new ServiceManager($config['dependencies']);
        $this->container->setService('config', new \ArrayObject($config));

        /** @var \Doctrine\DBAL\Connection $conn */
        $conn = $this->container->get('doctrine.connection.default');
        $userCreateSql = <<<EOT
create table groups (
  id INTEGER
  constraint groups_pk
  primary key autoincrement,
  name text
);
create table users (
  id INTEGER
  constraint users_pk
  primary key autoincrement,
  name text,
  group_id integer,
  status integer default 1
);
create unique index users_name_uindex on users (name);
create table foo (
  id INTEGER constraint foo_pk primary key autoincrement,
  name text,
  user_id integer 
);
create table bar (
  id INTEGER constraint bar_pk primary key autoincrement,
  name text,
  foo_id integer 
);
EOT;
        foreach (explode(';', $userCreateSql) as $sql) {
            if (! empty(trim($sql))) {
                $conn->prepare($sql)->executeStatement();
            }
        }
    }

    public function getConfig(): array
    {
        return [
            'dependencies' => [
                'abstract_factories' => [
                    DoctrineAbstractFactory::class,
                ],
                'factories' => [
                    SerializerInterface::class => function (ContainerInterface $container) {
                        return new Serializer([
                            new DoctrineEntityDenormalizer($container->get(ManagerRegistry::class)),
                            new ArrayDenormalizer(),
                            new ObjectNormalizer(
                                null,
                                new CamelCaseToSnakeCaseNameConverter(),
                                null,
                                new ReflectionExtractor(),
                            ),
                            new CollectionNormalizer(),
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
                    'attribute' => [
                        'class' => \Doctrine\ORM\Mapping\Driver\AttributeDriver::class,
                        'paths' => [
                            __DIR__ . '/Entity',
                        ],
                    ],
                    'default' => [
                        'class' => \Doctrine\Persistence\Mapping\Driver\MappingDriverChain::class,
                        'default_driver' => 'attribute',
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
