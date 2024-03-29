<?php declare(strict_types = 1);

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
        return $query->execute()->iterateAssociative();
    }

    public function count(): int
    {
        if ($this->count === null) {
            $countQuery = clone $this->query;
            $countQuery->setMaxResults(null);
            $countQuery->setFirstResult(0);
            if (! count($countQuery->getQueryPart('groupBy'))) {
                $countQuery->select('count(*)');
                $countQuery->resetQueryPart('orderBy');
            } else {
                $countQuery->resetQueryPart('orderBy');
                $countQuery = $this->query->getConnection()
                    ->createQueryBuilder()
                    ->select('count(*)')
                    ->from($countQuery);
            }

            $this->count = (int)$countQuery->execute()->fetchOne();
        }

        return $this->count;
    }
}
