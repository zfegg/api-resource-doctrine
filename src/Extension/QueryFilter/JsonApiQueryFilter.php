<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension\QueryFilter;

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
