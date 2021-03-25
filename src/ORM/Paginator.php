<?php

namespace Zfegg\ApiResourceDoctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Zfegg\ApiSerializerExt\Paginator\OffsetPaginatorInterface;
use Zfegg\ApiSerializerExt\Paginator\PaginatorPropertyTrait;

final class Paginator implements OffsetPaginatorInterface
{
    use PaginatorPropertyTrait;

    private QueryBuilder $query;

    private ?int $count = null;

    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    public function getIterator()
    {
        $query = $this->query;
        $query->setFirstResult(($this->currentPage - 1) * $this->itemsPerPage);
        $query->setMaxResults($this->itemsPerPage);

        foreach ($query->getQuery()->iterate() as $row) {
            yield current($row);
        };
    }

    public function count()
    {
        if ($this->count === null) {
            $countQuery = clone $this->query;
            if (! count($countQuery->getDQLPart('groupBy'))) {
                $countQuery->select('count(o) as c');
                $countQuery->resetDQLPart('orderBy');
                $countQuery->setFirstResult(null);
            } else {
                $countQuery->resetDQLPart('orderBy');
                $countQuery = $this->query->getEntityManager()
                    ->createQueryBuilder()
                    ->select('count(o)')
                    ->from($countQuery, 'o');
            }

            $result = $countQuery->getQuery()->getOneOrNullResult();
            $this->count = (int) $result['c'];
        }

        return $this->count;
    }
}
