<?php declare(strict_types = 1);

namespace ZfeggTest\ApiResourceDoctrine\Dbal;

use PHPUnit\Framework\TestCase;
use ZfeggTest\ApiResourceDoctrine\ResourceTestsTrait;
use ZfeggTest\ApiResourceDoctrine\SetUpContainerTrait;

class DbalResourceTest extends TestCase
{
    use SetUpContainerTrait;
    use ResourceTestsTrait;

    private array $config = [
        'doctrine.dbal-resources' => [
            'UsersResource' => [
                'table' => 'users',
                'primary' => 'id',
                'extensions' => [
                ],
            ],
        ],
    ];


    private function setConfigExtensions(array $extensions): void
    {
        $config = $this->container->get('config');

        $config['doctrine.dbal-resources']['UsersResource']['extensions'] = $extensions;
    }

    public function testCreateGetUpdateDelete(): void
    {
        /** @var \Zfegg\ApiRestfulHandler\Resource\ResourceInterface $resource */
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


    public function testDefaultQueryExtension(): void
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


    public function testQueryByContextExtension(): void
    {
        $extensions = [
            'query_by_context' => [
                'fields' => ['name']
            ]
        ];
        $this->setConfigExtensions($extensions);

        $context = ['name' => 'aaa'];
        $resource = $this->initResource($context);

        $result = $resource->getList($context);
        $this->assertIsIterable($result);
        $rows = iterator_to_array($result);
        $this->assertCount(1, $rows);
    }
}
