<?php


namespace Zfegg\ApiResourceDoctrine\Extension\QueryFilter;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;

class KendoQueryFilter extends AbstractQueryFilter
{

    private int $filterMaxDeep;

    public function __construct(
        array $fields,
        int $filterMaxDeep = 2,
        NamingStrategyInterface $namingStrategy = null
    )
    {
        parent::__construct($fields, $namingStrategy);
        $this->filterMaxDeep = $filterMaxDeep;
    }

    /**
     * @inheritDoc
     */
    public function getList($query, string $table, array $context)
    {
        $params = $context['query'] ?? [];

        $this->filter((array)($params['filter'] ?? []), $query);
    }

    /**
     * @param array $filter
     * @param ORMQueryBuilder|DbalQueryBuilder  $query
     */
    private function filter(array $filter, $query): void
    {
        $filter = $this->normalizeFilters($filter);

        $paramIndex = 1;

        if ($predicate = $this->parseFilters($filter, $query, $paramIndex)) {
            $query->andWhere($predicate);
        }
    }

    private function normalizeFilters(array $filter, int $deep = 0): ?array
    {
        $defaultFilters = $this->defaultFilters;
        if ($deep == 0) {
            $nFilter = ['filters' => []];
            if (isset($filter['filters']) && is_array($filter['filters'])) {
                $nFilter = $filter;
            } elseif(!empty($filter)) {
                $nFilter['filters'] = [$filter];
            }
            $filter = $nFilter;
        }

        if (
            isset($filter['field']) &&
            isset($filter['operator']) &&
            isset($this->fields[$filter['field']]) &&
            in_array($filter['operator'], $this->fields[$filter['field']]['op'])
        ) {
            if (isset($defaultFilters[$filter['field']])) {
                unset($defaultFilters[$filter['field']]);
            }
            return $filter;
        } elseif (isset($filter['filters']) && is_array($filter['filters']) && $deep < $this->filterMaxDeep) {
            $filters = [];
            foreach ($filter['filters'] as $subFilter) {
                if ($subFilter = $this->normalizeFilters($subFilter, $deep + 1)) {
                    $key = $subFilter['field'] . '#' . $subFilter['operator'];
                    $filters[$key] = $subFilter;
                }
            }

            if ($deep == 0) {
                $filters = array_merge($filters, ...array_values($defaultFilters));
            }

            $filter['filters'] = $filters;
            return $filter;
        }

        return null;
    }

    /**
     * @param ORMQueryBuilder|DbalQueryBuilder  $query
     * @return Composite|CompositeExpression|null
     */
    private function parseFilters(array $filter, $query, int &$paramIndex)
    {
        $predicates = [];
        foreach ($filter['filters'] as $subFilter) {
            if (! empty($subFilter['filters'])) {
                $predicates[] = $this->parseFilters($subFilter, $query, $paramIndex);
            } else {
                $predicates[] = $this->makePredicate($subFilter, $query, $paramIndex);
            }
        }

        if ($predicates) {
            $logic = isset($filter['logic']) && $filter['logic'] == 'or' ? 'orX' : 'andX';
            return $query->expr()->$logic(...$predicates);
        }

        return null;
    }
}
