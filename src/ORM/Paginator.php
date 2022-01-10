<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Zfegg\ApiSerializerExt\Paginator\OffsetPaginatorInterface;

final class Paginator implements OffsetPaginatorInterface
{

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

    /**
     * @inheritdoc
     */
    public function getIterator()
    {
        $query = $this->query;
        foreach ($query->getQuery()->iterate() as $row) {
            yield current($row);
        }
    }

    /**
     * @inheritdoc
     */
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
