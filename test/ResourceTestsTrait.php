<?php declare(strict_types = 1);

namespace ZfeggTest\ApiResourceDoctrine;

use Zfegg\ApiRestfulHandler\Resource\ResourceInterface;

trait ResourceTestsTrait
{

    private function initResource(array $context = []): ResourceInterface
    {
        /** @var ResourceInterface $resource */
        $resource = $this->container->get('UsersResource');
        $rs = $resource->create(['name' => 'test'], $context);
        $rs = $resource->create(['name' => 'test2'], $context);
        $rs = $resource->create(['name' => 'aaa'], $context);
        $rs = $resource->create(['name' => 'bbb'], $context);

        return $resource;
    }

    public function testGetListPagination(): void
    {
        $extensions = ['pagination' => ['pageSize' => 1],];
        $this->setConfigExtensions($extensions);

        $resource = $this->initResource();

        $result = $resource->getList();
        $this->assertCount(4, $result);

        $rows = iterator_to_array($result);

        $this->assertCount(1, $rows);
    }


    public function queryFilterExtensions(): array
    {
        $extensions = [
            'pagination' => [],
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
            'pagination' => [],
            'kendo_query_filter' => [
                'fields' => [
                    'name' => [
                        'op' => ["eq"],
                    ],
                ],
            ],
        ];
        return [
            'query_filter1' => [$extensions, ['name' => 'test', 'page_size' => '1'],],
            'query_filter2' => [$extensions, ['name' => ['eq' => 'test']],],
            'query_filter3' => [$extensions, ['name' => ['neq' => 'test']], 3],
            'query_filter4' => [$extensions, [],],
            'query_filter5' => [$extensions5, ['name' => 'test'], 2],
            'json_api_query_filter' => [$extensions6, ['filter' => ['name' => 'test']],],
            'kendo_query_filter' => [
                $extensions7,
                [
                    'filter' => ['field' => 'name', 'operator' => 'eq', 'value' => 'test'],
                    'page_size' => '1',
                ],
            ],
        ];
    }

    /**
     * Test query filter extensions.
     *
     * @dataProvider queryFilterExtensions
     */
    public function testGetListQueryFilter(array $extensions, array $query, int $count = 1): void
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
    public function testGetListSort(array $sort, string $firstRowName): void
    {
        $extensions = ['sort' => ['fields' => ['name' => 'desc']],];
        $this->setConfigExtensions($extensions);

        $resource = $this->initResource();

        $rows = $resource->getList(['query' => ['sort' => $sort]]);
        if (! is_array($rows)) {
            $rows = iterator_to_array($rows);
        }

        $this->assertCount(4, $rows);
        if (is_array($rows[0])) {
            $this->assertEquals($firstRowName, $rows[0]['name']);
        } else {
            $this->assertEquals($firstRowName, $rows[0]->getName());
        }
    }


    public function testQueryByContextExtension(): void
    {
        $extensions = [
            'query_by_context' => [
                'fields' => ['status']
            ]
        ];
        $this->setConfigExtensions($extensions);

        $context = ['status' => '1'];
        $resource = $this->initResource($context);

        $result = $resource->getList($context);
        $rows = iterator_to_array($result);
        $this->assertCount(4, $rows);
    }
}
