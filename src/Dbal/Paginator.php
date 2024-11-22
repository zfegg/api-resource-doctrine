<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Dbal;

use Doctrine\DBAL\Query\QueryBuilder;
use Zfegg\ApiSerializerExt\Paginator\OffsetPaginatorInterface;
use Zfegg\ApiSerializerExt\Paginator\PaginatorPropertyTrait;

final class Paginator implements OffsetPaginatorInterface
{
    use PaginatorPropertyTrait;

    private ?int $count = null;

    public function __construct(
        private QueryBuilder $query,
    ) {
    }

    public function getCurrentPage(): int
    {
        $query = $this->query;
        return ($query->getFirstResult() / $query->getMaxResults()) + 1;
    }

    public function getItemsPerPage(): int
    {
        return $this->query->getMaxResults();
    }

    public function getIterator(): \Traversable
    {
        $query = $this->query;
        return $query->executeQuery()->iterateAssociative();
    }

    public function count(): int
    {
        if ($this->count === null) {
            $countQuery = clone $this->query;
            $countQuery->setMaxResults(null);
            $countQuery->setFirstResult(0);

            if (! count(Utils::getQueryParts($countQuery, 'groupBy'))) {
                $countQuery->select('count(*)');
                $countQuery->resetGroupBy();
            } else {
                $countQuery->resetOrderBy();
                $countQueryWrap = clone $countQuery;
                $countQuery = $countQueryWrap
                    ->select('count(*)')
                    ->from($countQuery->getSQL());
            }

            $this->count = (int)$countQuery->fetchOne();
        }

        return $this->count;
    }
}
