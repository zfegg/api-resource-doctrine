<?php

namespace Zfegg\ApiResourceDoctrine\Dbal;

use Doctrine\DBAL\Query\QueryBuilder;
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

        $stmt = $query->execute();

        while($row = $stmt->fetchAssociative()) {
            yield $row;
        }
    }

    public function count()
    {
        if ($this->count === null) {
            $countQuery = clone $this->query;
            if (! count($countQuery->getQueryPart('groupBy'))) {
                $countQuery->select('count(*)');
                $countQuery->resetQueryPart('orderBy');
                $countQuery->setFirstResult(null);
            } else {
                $countQuery->resetQueryPart('orderBy');
                $countQuery = $this->query->getConnection()
                    ->createQueryBuilder()
                    ->select('count(*)')
                    ->from($countQuery);
            }

            $this->count = $countQuery->execute()->fetchOne();
        }

        return $this->count;
    }
}
