<?php


namespace Zfegg\ApiResourceDoctrine\Extension;


use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;
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
        foreach ($this->fields as $field => $attr) {
            if (is_int($field)) {
                $field = $attr;
            }
            if (! is_array($attr)) {
                $attr = [
                    'attr' => $field,
                ];
            }

            $field = $attr['field'] ?? (($rootAlias ? "$rootAlias." : '') . $field);
            $query->andWhere($query->expr()->eq($field, ":{$attr['attr']}"));
            $query->setParameter($attr['attr'], $context[$attr['attr']]);
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
