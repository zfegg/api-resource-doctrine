<?php

namespace ZfeggTest\ApiResourceDoctrine\Dbal;

use PHPUnit\Framework\TestCase;
use Zfegg\ApiResourceDoctrine\Dbal\DbalResource;
use Zfegg\ApiResourceDoctrine\ORM\OrmResource;
use ZfeggTest\ApiResourceDoctrine\ResourceTestsTrait;
use ZfeggTest\ApiResourceDoctrine\SetUpContainerTrait;

class DbalResourceTest extends TestCase
{
    use SetUpContainerTrait;
    use ResourceTestsTrait;

    private $config = [
        'doctrine.dbal-resources' => [
            'UsersResource' => [
                'table' => 'users',
                'primary' => 'id',
                'extensions' => [
                ],
            ],
        ],
    ];


    private function setConfigExtensions(array $extensions)
    {
        $config = $this->container->get('config');

        $config['doctrine.dbal-resources']['UsersResource']['extensions'] = $extensions;
    }

    public function testCreateGetUpdateDelete()
    {
        /** @var DbalResource $resource */
        $resource = $this->container->get('UsersResource');
        $result = $resource->create(['name' => 'test']);
        $this->assertEquals('test', $result['name']);

        $id = $result['id'];

        $result = $resource->get($id);
        $this->assertEquals('test', $result['name']);

        $result = $resource->update($id, ['name' => 'test123']);
        $this->assertEquals('test123', $result['name']);

        $result = $resource->update($id, ['name' => 'test456']);
        $this->assertEquals('test456', $result['name']);

        $resource->delete($id);

        $result = $resource->get($id);
        $this->assertFalse($result);
    }


    public function testDefaultQueryExtension()
    {
        $extensions = [
            'default_query' => [
                'parts' => [
                    ['andWhere', ['name="test"']],
                ]
            ]
        ];
        $this->setConfigExtensions($extensions);

        $resource = $this->initResource();

        $result = $resource->getList();
        $this->assertIsIterable($result);
        $rows = iterator_to_array($result);
        $this->assertCount(1, $rows);
    }


    public function testQueryByContextExtension()
    {
        $extensions = [
            'query_by_context' => [
                'fields' => ['name']
            ]
        ];
        $this->setConfigExtensions($extensions);

        $resource = $this->initResource();

        $result = $resource->getList(['name' => 'aaa']);
        $this->assertIsIterable($result);
        $rows = iterator_to_array($result);
        $this->assertCount(1, $rows);
    }
}
