<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension;

use Zfegg\ApiResourceDoctrine\Dbal\CursorPaginator as DbalPaginator;
use Zfegg\ApiResourceDoctrine\ORM\CursorPaginator as ORMPaginator;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;

class CursorPaginationExtension extends PaginationExtension
{

    /**
     * @inheritdoc
     */
    public function getList($query, string $table, array $context)
    {
        $class = $query instanceof ORMQueryBuilder ? ORMPaginator::class : DbalPaginator::class;
        $paginator = new $class($query);
        $paginator->setCursor($context['query']['cursor'] ?? null);
        $paginator->setItemsPerPage($this->getAllowedPageSize((int)($context['query']['page_size'] ?? 0)));

        return $paginator;
    }
}
