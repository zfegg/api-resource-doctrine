<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension\QueryFilter;

use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;
use Zfegg\ApiResourceDoctrine\Extension\ExtensionInterface;

abstract class AbstractQueryFilter implements QueryFilterInterface, ExtensionInterface
{
    protected array $fields;
    protected array $defaultFilters = [];

    private ?string $rootAlias = null;
    protected NamingStrategyInterface $namingStrategy;


    /**
     *
     * @param array $fields
     * <code>
     * [
     *   'key' => [
     *      'field' => 'o.keyName',
     *      'op' => ['eq', 'startswith'],
     *   ],
     *   'key' => [
     *      'expr' => ':key MEMBER OF o.keys',
     *   ],
     * ]
     * </code>
     */
    public function __construct(array $fields, ?NamingStrategyInterface $namingStrategy = null)
    {
        $this->fields = self::normalize($fields);
        $this->initDefaultFilters();
        $this->namingStrategy = $namingStrategy ?: new NonNamingStrategy();
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
            } elseif ($config === true) {
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
     * @param \Doctrine\DBAL\Query\QueryBuilder|\Doctrine\ORM\QueryBuilder  $query
     *
     * @return \Doctrine\DBAL\Query\Expression\CompositeExpression|\Doctrine\ORM\Query\Expr\Composite
     */
    protected function makePredicate(array $filter, $query)
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

        if (isset($this->fields[$filter['field']]['expr'])) {
            $query->setParameter($filter['field'], $value, $this->fields[$filter['field']]['type'] ?? null);
            return $this->fields[$filter['field']]['expr'];
        }

        $op = self::OPERATORS[$op];
        $field = $this->fields[$filter['field']]['field'] ??
            (($rootAlias ? "$rootAlias." : '') . $this->namingStrategy->columnName($filter['field']));
        if (in_array($op, ['isNull', 'isNotNull'])) {
            $expr = $query->expr()->$op($field);
        } elseif (! $query instanceof ORMQueryBuilder && $op === 'in') {
            if ($value && is_array($value)) {
                $y = [];

                foreach ($value as $idx => $val) {
                    $paramName = "{$filter['field']}_$idx";
                    $y[] = ":$paramName";
                    $query->setParameter(
                        $paramName,
                        $val,
                        $this->fields[$filter['field']]['type'] ?? null
                    );
                }
                $expr = $query->expr()->in($field, $y);
            }
        } else {
            $paramName = "{$filter['field']}__{$filter['operator']}";
            $expr = $query->expr()->$op($field, ":{$paramName}");
            $query->setParameter($paramName, $value, $this->fields[$filter['field']]['type'] ?? null);
        }

        return $expr;
    }

    /**
     * @param \Doctrine\DBAL\Query\QueryBuilder|\Doctrine\ORM\QueryBuilder $query
     */
    protected function getRootAlias($query): ?string
    {
        if ($this->rootAlias !== null) {
            return $this->rootAlias;
        }

        if ($query instanceof ORMQueryBuilder) {
            $rootAlias = $query->getRootAliases()[0];
        } else {
            $rootAlias = current((array)$query->getQueryPart('from'))['alias'] ?? null;
        }

        return $this->rootAlias = $rootAlias;
    }

    /**
     * @inheritdoc
     */
    public function get($query, string $table, array $context)
    {
    }
}
