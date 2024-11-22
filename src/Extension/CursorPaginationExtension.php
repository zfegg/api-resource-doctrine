<?php

declare(strict_types = 1);

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
        if ($this->pageable
            && isset($context['query']['pageable'])
            && boolval($context['query']['pageable']) === false
        ) {
            return ;
        }

        if (in_array($context['format'] ?? '', $this->disabledFormats)) {
            return ;
        }

        $class = $query instanceof ORMQueryBuilder ? ORMPaginator::class : DbalPaginator::class;
        $paginator = new $class($query);
        if (isset($context['query']['cursor'])) {
            $paginator->setCursor((int) $context['query']['cursor']);
        }
        $paginator->setItemsPerPage($this->getAllowedPageSize((int)($context['query']['page_size'] ?? 0)));

        return $paginator;
    }
}
