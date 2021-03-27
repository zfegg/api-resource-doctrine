<?php


namespace ZfeggTest\ApiResourceDoctrine;


use Zfegg\ApiResourceDoctrine\Dbal\DbalResource;
use Zfegg\ApiResourceDoctrine\ORM\OrmResource;

trait ResourceTestsTrait
{

    /**
     * @return DbalResource|OrmResource
     */
    private function initResource(array $context = [])
    {
        /** @var DbalResource $resource */
        $resource = $this->container->get('UsersResource');
        $rs = $resource->create(['name' => 'test'], $context);
        $rs = $resource->create(['name' => 'test2'], $context);
        $rs = $resource->create(['name' => 'aaa'], $context);
        $rs = $resource->create(['name' => 'bbb'], $context);

        return $resource;
    }

    public function testGetListPagination()
    {
        $extensions = ['pagination' => ['pageSize' => 1],];
        $this->setConfigExtensions($extensions);

        $resource = $this->initResource();

        $result = $resource->getList();
        $this->assertCount(4, $result);
        $this->assertIsIterable($result);

        $rows = iterator_to_array($result);

        $this->assertCount(1, $rows);
    }


    public function queryFilterExtensions()
    {
        $extensions = [
            'query_filter' => [
                'fields' => [
                    'name' => [
                        'op' => ["eq","neq","lt","lte","gt","gte","startswith","endswith","contains","isnull"],
                        'default' => 'test',
                    ],
                ],
            ],
        ];
        $extensions5 = [
            'query_filter' => [
                'fields' => [
                    'name' => [
                        'op' => ["startswith"],
                        'default' => 'test',
                    ],
                ],
            ],
        ];
        $extensions6 = [
            'json_api_query_filter' => [
                'fields' => [
                    'name' => [
                        'op' => ["eq"],
                        'default' => 'test',
                    ],
                ],
            ],
        ];
        $extensions7 = [
            'kendo_query_filter' => [
                'fields' => [
                    'name' => [
                        'op' => ["eq"],
                    ],
                ],
            ],
        ];
        return [
            'query_filter1' => [$extensions, ['name' => 'test'],],
            'query_filter2' => [$extensions, ['name' => ['eq' => 'test']],],
            'query_filter3' => [$extensions, ['name' => ['neq' => 'test']], 3],
            'query_filter4' => [$extensions, [],],
            'query_filter5' => [$extensions5, ['name' => 'test'], 2],
            'json_api_query_filter' => [$extensions6, ['filter' => ['name' => 'test']],],
            'kendo_query_filter' => [$extensions7, ['filter' => ['field' => 'name', 'operator' => 'eq', 'value' => 'test']],],
        ];
    }

    /**
     * Test query filter extensions.
     *
     * @dataProvider queryFilterExtensions
     */
    public function testGetListQueryFilter(array $extensions, array $query, int $count = 1)
    {
        $this->setConfigExtensions($extensions);

        $resource = $this->initResource();

        $result = $resource->getList(['query' => $query]);
        $this->assertIsIterable($result);
        if (is_array($result)) {
            $rows = $result;
        } else {
            $rows = iterator_to_array($result);
        }
        $this->assertCount($count, $rows);
    }

    public function sortExtensions(): array
    {
        return [
            'default' => [[], 'test2'],
            'sort_asc' => [['name' => 'asc'], 'aaa'],
            'sort_desc' => [['name' => 'desc'], 'test2'],
        ];
    }

    /**
     *
     * @dataProvider sortExtensions
     */
    public function testGetListSort(array $sort, string $firstRowName)
    {
        $extensions = ['sort' => ['fields' => ['name' => 'desc']],];
        $this->setConfigExtensions($extensions);

        $resource = $this->initResource();

        $result = $resource->getList(['query' => ['sort' => $sort]]);
        $this->assertIsIterable($result);
        if (is_array($result)) {
            $rows = $result;
            $this->assertEquals($firstRowName, $rows[0]->getName());
        } else {
            $rows = iterator_to_array($result);
            $this->assertCount(4, $rows);
            $this->assertEquals($firstRowName, $rows[0]['name']);
        }
    }

}