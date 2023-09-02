<?php declare(strict_types = 1);

namespace ZfeggTest\ApiResourceDoctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ZfeggTest\ApiResourceDoctrine\Entity\Group;
use ZfeggTest\ApiResourceDoctrine\Entity\Role;
use ZfeggTest\ApiResourceDoctrine\ResourceTestsTrait;
use ZfeggTest\ApiResourceDoctrine\SetUpContainerTrait;
use ZfeggTest\ApiResourceDoctrine\Entity\User;

class OrmResourceTest extends TestCase
{
    use SetUpContainerTrait;
    use ResourceTestsTrait;

    private array $config = [
        'doctrine.orm-resources' => [
            'RolesResource' => [
                'entity' => Role::class,
            ],
            'UsersResource' => [
                'entity' => User::class,
                'parent' => 'RolesResource',
                'extensions' => [
                    'kendo_query_filter' => [
                        'fields' => [
                            'name' => [
                                'op' => ['eq', 'in']
                            ],
                        ]
                    ],
                ],
            ],
            'GroupsResource' => [
                'entity' => Group::class,
                'extensions' => [
                    'kendo_query_filter' => [
                        'fields' => [
                            'name' => [
                                'op' => ['eq']
                            ],
                            'users' => [
                                'expr' => ':users member of o.users'
                            ],
                        ]
                    ],
                ],
            ],
        ],
    ];

    private function setConfigExtensions(array $extensions): void
    {
        $config = $this->container->get('config');

        $config['doctrine.orm-resources']['UsersResource']['extensions'] = $extensions;
    }

//    public function testGetList()
//    {
//        /** @var OrmResource $resource */
//        $resource = $this->container->get('UsersResource');
//        $resource->create(['name' => 'test']);
//        $resource->create(['name' => 'test2']);
//
////        $result = $resource->getList(['query' => ['name' => ['eq' => 'test']]]);
//        $result = $resource->getList([
//            'query' => ['filter' => ['field' => 'name', 'operator' => 'eq', 'value' => 'test']]]);
//        $this->assertCount(1, $result);
//        $this->assertIsIterable($result);
//
//        $row = iterator_to_array($result)[0];
//        $this->assertEquals('test', $row->getName());
//    }

    public function testCreateGetUpdateDelete(): void
    {

        $roleResource = $this->container->get('RolesResource');
        $role = $roleResource->create(['name' => 'test']);
        /** @var \Zfegg\ApiRestfulHandler\ResourceInterface $resource */
        $resource = $this->container->get('UsersResource');

        $context = [
            'role_id' => $role->getId(),
        ];
        $result = $resource->create(['name' => 'test', 'group' => 1], $context);
        $this->assertInstanceOf(User::class, $result);

        $id = $result->getId();

        $result = $resource->get($id, $context);
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('test', $result->getName());

        $result = $resource->update($id, ['name' => 'test123'], $context);
        $this->assertEquals('test123', $result->getName());

        $result = $resource->update($id, ['name' => 'test456'], $context);
        $this->assertEquals('test456', $result->getName());

        $resource->delete($id);
        $this->assertNull($resource->get($id, $context));

        $result = $resource->getList([
            'query' => [
                'filter' => [
                    'filters' => [
                        [
                            'field' => 'name',
                            'operator' => 'in',
                            'value' => ['test123', 'foo', 'bar']
                        ]
                    ]
                ]
            ],
        ] + $context);
        $this->assertIsIterable($result);
    }

    public function testAssociationCreateGetUpdateDelete(): void
    {
        /** @var \Zfegg\ApiRestfulHandler\ResourceInterface $resource */
        $resource = $this->container->get('GroupsResource');
        $result = $resource->create(['name' => 'test']);
        $this->assertInstanceOf(Group::class, $result);

        $id = $result->getId();

        $result = $resource->get($id);
        $this->assertInstanceOf(Group::class, $result);
        $this->assertEquals('test', $result->getName());

        $result = $resource->update($id, ['name' => 'test123']);
        $this->assertEquals('test123', $result->getName());

        $result = $resource->getList([
            'query' => [
                'filter' => [
                    'filters' => [
                        [
                            'field' => 'users',
                            'operator' => 'eq',
                            'value' => '123'
                        ],
                        [
                            'field' => 'name',
                            'operator' => 'eq',
                            'value' => 'test123'
                        ],
                    ]
                ]
            ]
        ]);
        $this->assertIsIterable($result);

        $resource->delete($id);
        $this->assertNull($resource->get($id));
    }


    public function testDefaultQueryExtension(): void
    {
        $extensions = [
            'default_query' => [
                'parts' => [
                    ['andWhere', ["o.name='test'"]],
                ]
            ]
        ];
        $this->setConfigExtensions($extensions);

        $resource = $this->initResource();

        $result = $resource->getList();
        $this->assertIsIterable($result);
        $this->assertCount(1, $result);
    }

    public function testManyToMany(): void
    {
        $em = $this->container->get(EntityManagerInterface::class);

        $role = new Role();
        $role->setName("role1");
        $em->persist($role);
        $role = new Role();
        $role->setName("role2");
        $em->persist($role);
        $em->flush();
        /** @var \Zfegg\ApiRestfulHandler\ResourceInterface $resource */
        $resource = $this->container->get('UsersResource');
        $result = $resource->create(['name' => 'test', 'roles' => [1]]);
        $this->assertInstanceOf(User::class, $result);

        $id = $result->getId();

        $result = $resource->get($id);
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('test', $result->getName());

        $result = $resource->update($id, ['name' => 'test123', 'roles' => [1,2]]);
        $this->assertEquals('test123', $result->getName());

        $result = $resource->getList([
            'query' => [
                'filter' => [
                    'filters' => [
                        [
                            'field' => 'users',
                            'operator' => 'eq',
                            'value' => '123'
                        ],
                        [
                            'field' => 'name',
                            'operator' => 'eq',
                            'value' => 'test123'
                        ],
                    ]
                ]
            ]
        ]);
        $this->assertIsIterable($result);

        $resource->delete($id);
        $this->assertNull($resource->get($id));
    }
}
