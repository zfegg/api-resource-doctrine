<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Zfegg\ApiSerializerExt\Paginator\CursorPaginatorInterface;
use Zfegg\ApiSerializerExt\Paginator\CursorPropertyTrait;

final class CursorPaginator implements CursorPaginatorInterface
{
    use CursorPropertyTrait;

    private QueryBuilder $query;

    private ?array $data = null;

    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    private function initData(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $query = clone $this->query;
        $query->setFirstResult(0);
        $query->setMaxResults($this->itemsPerPage + 1);

        $className = $query->getRootEntities()[0];
        $rootAlias = $query->getRootAliases()[0];
        $em = $this->query->getEntityManager();
        $meta = $em->getClassMetadata($className);
        $idField = $meta->getSingleIdentifierFieldName();
        $fullIdField = $rootAlias . '.' . $idField;

        $spliceIdOrders = [];
        $idDesc = $this->isOrderByIdentityDesc($fullIdField, $spliceIdOrders);

        if ($this->cursor) {
            $op = $idDesc ? 'lte' : 'gte';
            $query->andWhere($query->expr()->$op($fullIdField, ':cursor'));
            $query->setParameter('cursor', $this->getCursor());
        }

        $this->data = $query->getQuery()->getResult();

        // Set next cursor
        if (isset($this->data[$this->itemsPerPage])) {
            $nextCursorRow = $this->data[$this->itemsPerPage];

            if (is_object($nextCursorRow)) {
                $this->nextCursor = $meta->getFieldValue($nextCursorRow, $idField);
            } elseif (is_array($nextCursorRow)) {
                $this->nextCursor = $nextCursorRow[$idField];
            }
            unset($this->data[$this->itemsPerPage]);
        }

        // Set prev cursor
        if ($this->cursor) {
            $op = $idDesc ? 'gt' : 'lt';
            $prevQuery = clone $this->query;

            $prevQuery->select($fullIdField);
            $prevQuery->andWhere($query->expr()->$op($fullIdField, ':cursor'));
            $prevQuery->setParameter('cursor', $this->getCursor());
            $prevQuery->resetDQLPart('orderBy');
            array_map([$prevQuery, 'addOrderBy'], $spliceIdOrders);
            if (! $idDesc) {
                $prevQuery->addOrderBy($fullIdField, 'desc');
            }
            $prevQuery->setMaxResults($this->itemsPerPage);
            $prevCursor = $prevQuery->getQuery()->getSingleColumnResult();
            $prevCursor = $prevCursor ? array_pop($prevCursor) : null;

            $this->prevCursor = $prevCursor ? (int) $prevCursor : null;
        }

        return $this->data;
    }

    private function isOrderByIdentityDesc(string $idField, array &$findOrders): bool
    {
        $query = $this->query;
        /** @var \Doctrine\ORM\Query\Expr\OrderBy[] $orders */
        $orders = $query->getDQLPart('orderBy');

        foreach ($orders as $idx => $order) {
            foreach ($order->getParts() as $part) {
                [$field, $sort] = explode(' ', $part);
                if ($field == $idField) {
                    array_splice($orders, $idx, 1);
                    $findOrders = $orders;
                    return $sort == 'desc';
                }
            }
        }

        return false;
    }

    public function getIterator(): \Traversable
    {
        foreach ($this->initData() as $item) {
            yield $item;
        }
    }


    public function getPrevCursor(): ?int
    {
        $this->initData();
        return $this->prevCursor;
    }

    public function getNextCursor(): ?int
    {
        $this->initData();
        return $this->nextCursor;
    }
}
