<?php

namespace Zfegg\ApiResourceDoctrine\Extension;


use Zfegg\ApiResourceDoctrine\Dbal\Paginator as DbalPaginator;
use Zfegg\ApiResourceDoctrine\ORM\Paginator as ORMPaginator;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;
use Doctrine\Dbal\Query\QueryBuilder as DbalQueryBuilder;

class PaginationExtension implements ExtensionInterface
{
    private int $pageSize;

    private array $pageSizeRange;

    /**
     * PaginationExtension constructor.
     * @param int $pageSize
     * @param int[] $pageSizeRange
     */
    public function __construct(int $pageSize = 20, array $pageSizeRange = [20, 50, 100, 200])
    {
        $this->pageSize = $pageSize;
        $this->pageSizeRange = $pageSizeRange;
    }

    /**
     * @param ORMQueryBuilder|DbalQueryBuilder  $query
     * @param string $table
     * @param array $context
     * @return ORMPaginator
     */
    public function getList($query, string $table, array $context)
    {
        $class = $query instanceof ORMQueryBuilder ? ORMPaginator::class : DbalPaginator::class;
        $paginator = new $class($query);
        $paginator->setCurrentPage($context['query']['page'] ?? 1);
        $paginator->setItemsPerPage($this->getAllowedPageSize($context['query']['page_size'] ?? null));

        return $paginator;
    }

    private function getAllowedPageSize(?int $pageSize): int
    {
        if (in_array($pageSize, $this->pageSizeRange)) {
            return $pageSize;
        }

        return $this->pageSize;
    }
}