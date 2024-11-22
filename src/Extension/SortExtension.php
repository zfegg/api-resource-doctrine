<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension;

use Zfegg\ApiResourceDoctrine\ORM\OrmResource;

class SortExtension implements ExtensionInterface
{
    private array $defaultSorts = [];
    private array $fields;

    public function __construct(array $fields)
    {
        $this->fields = $this->normalizeFields($fields);
    }

    private function normalizeFields(array $fields): array
    {
        $defaultSorts = [];
        $nFields = [];
        foreach ($fields as $name => $config) {
            if (is_int($name)) {
                $name = $config;
                $config = [];
            }
            if (is_string($config)) {
                $defaultSorts[$name] = $config;
                $config = [
                    'dir' => $config,
                ];
            } elseif ($config === true) {
                $config = [];
            }

            $nFields[$name] = $config;
        }

        $this->defaultSorts = $defaultSorts;

        return $nFields;
    }

    /**
     * @inheritDoc
     */
    public function getList($query, string $table, array $context)
    {
        $this->sort(
            (array)($context['sort'] ?? $context['query']['sort'] ?? []),
            $query,
            $context[OrmResource::ROOT_ALIAS] ?? null
        );
    }

    /**
     * @param \Doctrine\DBAL\Query\QueryBuilder|\Doctrine\ORM\QueryBuilder  $query
     */
    protected function sort(array $sort, $query, ?string $rootAlias): void
    {
        // Normalize sortable
        $sort = $sort + $this->defaultSorts;
        foreach ($sort as $field => $order) {
            if (is_int($field) && isset($order['field']) && isset($order['dir'])) {
                $field = $order['field'];
                $order = $order['dir'];
            }
            if (! in_array($order, ['desc', 'asc', 'false']) ||
                ! isset($this->fields[$field])
            ) {
                continue;
            }

            if ($order != 'false') {
                $field = $this->fields[$field]['field'] ?? (($rootAlias ? "$rootAlias." : '') . $field);
                $query->addOrderBy($field, $order);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function get($query, string $table, array $context)
    {
    }
}
