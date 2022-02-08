<?php declare(strict_types = 1);

namespace ZfeggTest\ApiResourceDoctrine\ORM;

use PHPUnit\Framework\TestCase;
use ZfeggTest\ApiResourceDoctrine\ResourceTestsTrait;
use ZfeggTest\ApiResourceDoctrine\SetUpContainerTrait;
use ZfeggTest\ApiResourceDoctrine\Entity\User;

class OrmResourceTest extends TestCase
{
    use SetUpContainerTrait;
    use ResourceTestsTrait;

    private array $config = [
        'doctrine.orm-resources' => [
            'UsersResource' => [
                'entity' => User::class,
                'extensions' => [
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
        /** @var \Zfegg\ApiRestfulHandler\Resource\ResourceInterface $resource */
        $resource = $this->container->get('UsersResource');
        $result = $resource->create(['name' => 'test']);
        $this->assertInstanceOf(User::class, $result);

        $id = $result->getId();

        $result = $resource->get($id);
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('test', $result->getName());

        $result = $resource->update($id, ['name' => 'test123']);
        $this->assertEquals('test123', $result->getName());

        $result = $resource->update($id, ['name' => 'test456']);
        $this->assertEquals('test456', $result->getName());

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
}
