<?php

namespace Zfegg\ApiResourceDoctrine\Extension;


use Zfegg\ApiResourceDoctrine\Dbal\Paginator as DbalPaginator;
use Zfegg\ApiResourceDoctrine\ORM\Paginator as ORMPaginator;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;

class PaginationExtension implements ExtensionInterface
{
    protected int $pageSize;

    protected array $pageSizeRange;

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
     * @inheritdoc
     */
    public function getList($query, string $table, array $context)
    {
        $class = $query instanceof ORMQueryBuilder ? ORMPaginator::class : DbalPaginator::class;
        $paginator = new $class($query);
        $paginator->setCurrentPage($context['query']['page'] ?? 1);
        $paginator->setItemsPerPage($this->getAllowedPageSize($context['query']['page_size'] ?? null));

        return $paginator;
    }

    protected function getAllowedPageSize(?int $pageSize): int
    {
        if (in_array($pageSize, $this->pageSizeRange)) {
            return $pageSize;
        }

        return $this->pageSize;
    }

    /**
     * @inheritdoc
     */
    public function get($query, string $table, array $context)
    {
    }
}