<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension\QueryFilter;

class KendoQueryFilter extends AbstractQueryFilter
{

    private int $filterMaxDeep;

    public function __construct(
        array $fields,
        int $filterMaxDeep = 2,
        ?NamingStrategyInterface $namingStrategy = null
    ) {
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
     * @param \Doctrine\DBAL\Query\QueryBuilder|\Doctrine\ORM\QueryBuilder  $query
     */
    private function filter(array $filter, $query): void
    {
        $defaultFilters = $this->defaultFilters;
        $filter = $this->normalizeFilters($filter, $defaultFilters);

        if ($predicate = $this->parseFilters($filter, $query)) {
            $query->andWhere($predicate);
        }
    }

    private function normalizeFilters(array $filter, array &$defaultFilters, int $deep = 0): ?array
    {
        if ($deep == 0) {
            $nFilter = ['filters' => []];
            if (isset($filter['filters']) && is_array($filter['filters'])) {
                $nFilter = $filter;
            } elseif (! empty($filter)) {
                $nFilter['filters'] = [$filter];
            }
            $filter = $nFilter;
        }

        if (isset($filter['field']) &&
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
                if ($subFilter = $this->normalizeFilters($subFilter, $defaultFilters, $deep + 1)) {
                    if (isset($subFilter['filters'])) {
                        $filters[] = $subFilter;
                    } else {
                        $key = $subFilter['field'] . '#' . $subFilter['operator'];
                        $filters[$key] = $subFilter;
                    }
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
     * @param \Doctrine\DBAL\Query\QueryBuilder|\Doctrine\ORM\QueryBuilder  $query
     * @return  \Doctrine\DBAL\Query\Expression\CompositeExpression|\Doctrine\ORM\Query\Expr\Composite|null
     */
    private function parseFilters(array $filter, $query)
    {
        $predicates = [];
        foreach ($filter['filters'] as $subFilter) {
            if (! empty($subFilter['filters'])) {
                $predicates[] = $this->parseFilters($subFilter, $query);
            } else {
                $predicates[] = $this->makePredicate($subFilter, $query);
            }
        }

        if ($predicates) {
            $logic = isset($filter['logic']) && $filter['logic'] == 'or' ? 'orX' : 'andX';
            return $query->expr()->$logic(...$predicates);
        }

        return null;
    }
}
