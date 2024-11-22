<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension\QueryFilter;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Zfegg\ApiResourceDoctrine\Dbal\Utils;
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
     * @return \Doctrine\DBAL\Query\Expression\CompositeExpression|\Doctrine\ORM\Query\Expr\Composite
     */
    protected function makePredicate(array $filter, ORMQueryBuilder|DBALQueryBuilder $query)
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
        } else {
            if (in_array($op, ['in', 'notIn'])) {
                $paramType = $this->fields[$filter['field']]['type'] ?? ArrayParameterType::STRING;
            } elseif ($query instanceof ORMQueryBuilder) {
                $paramType = $this->fields[$filter['field']]['type'] ?? null;
            } else {
                $paramType = $this->fields[$filter['field']]['type'] ?? ParameterType::STRING;
            }
            $paramName = "{$filter['field']}__{$filter['operator']}";
            $expr = $query->expr()->$op($field, ":{$paramName}");
            $query->setParameter($paramName, $value, $paramType);
        }

        return $expr;
    }

    protected function getRootAlias(ORMQueryBuilder|DBALQueryBuilder $query): ?string
    {
        if ($this->rootAlias !== null) {
            return $this->rootAlias;
        }

        if ($query instanceof ORMQueryBuilder) {
            $rootAlias = $query->getRootAliases()[0];
        } else {
            $from = Utils::getQueryParts($query, 'from');
            $rootAlias = $from[0]->alias ?? null;
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
