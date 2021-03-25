<?php


namespace Zfegg\ApiResourceDoctrine\Extension\QueryFilter;

use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;

class JsonApiQueryFilter extends DefaultQueryFilter
{
    /**
     * @inheritDoc
     */
    public function getList($query, string $table, array $context)
    {
        $params = $context['query'] ?? [];

        $this->filter($params['filter'] ?? [], $query);
    }
}
