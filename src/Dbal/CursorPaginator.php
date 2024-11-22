<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Dbal;

use Doctrine\DBAL\Query\QueryBuilder;
use Zfegg\ApiSerializerExt\Paginator\CursorPaginatorInterface;
use Zfegg\ApiSerializerExt\Paginator\CursorPropertyTrait;

final class CursorPaginator implements CursorPaginatorInterface
{
    use CursorPropertyTrait;

    private QueryBuilder $query;

    private string $expr;

    /**
     * CursorPaginator constructor.
     */
    public function __construct(QueryBuilder $query, string $expr)
    {
        $this->query = $query;
        $this->expr = $expr;
    }

    public function getIterator(): \Traversable
    {
        $query = $this->query;
        if ($this->getCursor()) {
            $query->andWhere($this->expr);
            $query->setParameter('cursor', $this->getCursor());
        }
        $query->setFirstResult(0);
        $query->setMaxResults($this->itemsPerPage);

        $stmt = $query->execute();

        while ($row = $stmt->fetchAssociative()) {
            yield $row;
        }
    }
}
