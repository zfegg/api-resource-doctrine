<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension;

use Zfegg\ApiResourceDoctrine\ORM\OrmResource;

class QueryByContextExtension implements ExtensionInterface
{
    private array $fields;

    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }

    /**
     * @inheritDoc
     */
    public function getList($query, string $table, array $context)
    {
        $rootAlias = $context[OrmResource::ROOT_ALIAS] ?? null;
        foreach ($this->fields as $key => $attr) {
            if (is_int($key) && is_string($attr)) {
                $key = $attr;
                $attr = [
                    'attr' => $attr,
                ];
            }
            if (is_string($attr)) {
                $attr = [
                    'attr' => $attr,
                ];
            }

            $field = $attr['field'] ?? (($rootAlias ? "$rootAlias." : '') . $key);
            $query->andWhere($query->expr()->eq($field, ":{$key}"));
            $query->setParameter($key, $context[$attr['attr']]);
        };
    }

    /**
     * @inheritDoc
     */
    public function get($query, string $table, array $context)
    {
        $this->getList($query, $table, $context);
    }
}
