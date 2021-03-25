<?php


namespace Zfegg\ApiResourceDoctrine\Extension\QueryFilter;


use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use Doctrine\ORM\Query\Expr\Composite;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;
use Zfegg\ApiResourceDoctrine\Extension\ExtensionInterface;

abstract class AbstractQueryFilter implements QueryFilterInterface, ExtensionInterface
{
    protected array $fields;
    protected array $defaultFilters = [];

    private ?string $rootAlias = '';

    public function __construct(array $fields)
    {
        $this->fields = self::normalize($fields);
        $this->initDefaultFilters();
    }

    protected function initDefaultFilters(): void
    {
        $defaultFilters = [];

        foreach ($this->fields as $field => $config) {
            if (! isset($config['default'])) {
                continue;
            }

            $filter = ['field' => $field];
            if (isset($config['default'])) {
                if (is_array($config['default'])) {
                    foreach ($config['default'] as $op => $value) {
                        $filter['operator'] = $op;
                        $filter['value'] = $value;
                        $defaultFilters[$field][] = $filter;
                    }
                } else {
                    $filter['operator'] = current($config['op']);
                    $filter['value'] = $config['default'];

                    $defaultFilters[$field][] = $filter;
                }
            }

        }

        $this->defaultFilters = $defaultFilters;
    }

    protected static function normalize(array $fields): array
    {
        $normalizedFields = [];
        foreach ($fields as $key => $config) {
            if (is_int($key)) {
                $key = $config;
                $config = [
                    'op' => ['eq'],
                ];
            } else if ($config === true) {
                $config = [
                    'op' => ['eq'],
                ];
            }
            $config['op'] = array_intersect($config['op'] ?? ['eq'], array_keys(self::OPERATORS));
            $normalizedFields[$key] = $config;
        }

        return $normalizedFields;
    }

    /**
     * @param ORMQueryBuilder|DbalQueryBuilder  $query
     * @return Composite|CompositeExpression
     */
    protected function makePredicate(array $filter, $query, int &$paramIndex)
    {
        $rootAlias = $this->getRootAlias($query);
        $op = $filter['operator'];
        $value = $filter['value'] ?? null;

        if (in_array($op, ['startswith', 'endswith', 'contains'])) {
            $value = str_replace(
                ['_', '%'],
                ['\\_', '\\%'],
                $value
            );
        }
        switch ($op) {
            case 'startswith':
                $value .= '%';
                break;
            case 'endswith':
                $value = '%' . $value;
                break;
            case 'contains':
                $value = '%' . $value . '%';
                break;
            default:
                break;
        }

        $op = self::OPERATORS[$op];
        $field = $this->fields[$filter['field']]['field'] ?? (($rootAlias ? "$rootAlias." : '') . $filter['field']);
        if (in_array($op, ['isNull', 'isNotNull'])) {
            $expr = $query->expr()->$op($field);
        } else {
            $expr = $query->expr()->$op($field, "?$paramIndex");
            $query->setParameter($paramIndex, $value);
            $paramIndex++;
        }

        return $expr;
    }

    protected function getRootAlias($query)
    {
        if ($this->rootAlias !== '') {
            return $this->rootAlias;
        }

        if ($query instanceof ORMQueryBuilder) {
            $rootAlias = $query->getRootAliases()[0];
        } else {
            $rootAlias = current((array)$query->getQueryPart('from'))['alias'] ?? null;
        }

        return $this->rootAlias = $rootAlias;
    }
}
