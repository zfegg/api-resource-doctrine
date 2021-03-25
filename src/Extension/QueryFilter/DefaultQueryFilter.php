<?php


namespace Zfegg\ApiResourceDoctrine\Extension\QueryFilter;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;

class DefaultQueryFilter extends AbstractQueryFilter
{

    /**
     * @inheritDoc
     */
    public function getList($query, string $table, array $context)
    {
        $params = $context['query'] ?? [];

        $this->filter($params, $query);
    }

    /**
     * @param array $filter
     * @param ORMQueryBuilder|DbalQueryBuilder  $query
     */
    protected function filter(array $params, $query): void
    {

        $filters = $this->normalizeFilters($params);

        $paramIndex = 1;

        foreach ($filters as $filter) {
            $query->andWhere($this->makePredicate($filter, $query, $paramIndex));
        }
    }

    protected function normalizeFilters(array $params): ?array
    {
        $defaultFilters = $this->defaultFilters;
        $filters = [];

        foreach ($params as $key => $value) {
            if (! isset($this->fields[$key])) {
                continue;
            }

            unset($defaultFilters[$key]);
            if (is_array($value) && ! is_int(key($value))) {
                foreach ($value as $op => $subValue) {
                    if (in_array($op, $this->fields[$key]['op'])) {
                        $filters[] = [
                            'field' => $key,
                            'operator' => $op,
                            'value' => $subValue,
                        ];
                    }
                }
            } else {
                $filters[] = [
                    'field' => $key,
                    'operator' => current($this->fields[$key]['op']),
                    'value' => $value,
                ];
            }
        }

        return $filters + array_merge(...array_values($defaultFilters));
    }
}
