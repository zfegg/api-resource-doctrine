<?php

namespace ZfeggTest\ApiResourceDoctrine\Extension;

use Doctrine\DBAL\Query\QueryBuilder;
use Zfegg\ApiResourceDoctrine\Dbal\Paginator as DbalPaginator;
use Zfegg\ApiResourceDoctrine\Extension\PaginationExtension;
use PHPUnit\Framework\TestCase;

class PaginationExtensionTest extends TestCase
{

    public function testPageable(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $pagination = new PaginationExtension(pageable: true);

        $result = $pagination->getList($qb, '', []);
        self::assertInstanceOf(DbalPaginator::class, $result);

        $result = $pagination->getList($qb, '', ['query' => ['pageable' => '0']]);
        self::assertNull($result);
    }

    public function testDisabledFormats(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $pagination = new PaginationExtension(disabledFormats: ['csv']);

        $result = $pagination->getList($qb, '', []);
        self::assertInstanceOf(DbalPaginator::class, $result);

        $result = $pagination->getList($qb, '', ['format' => 'csv']);
        self::assertNull($result);
    }
}
