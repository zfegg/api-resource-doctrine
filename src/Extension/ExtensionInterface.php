<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension;

interface ExtensionInterface
{

    /**
     * @param \Doctrine\DBAL\Query\QueryBuilder|\Doctrine\ORM\QueryBuilder  $query
     * @return iterable|void
     */
    public function getList($query, string $table, array $context);

    /**
     * @param \Doctrine\DBAL\Query\QueryBuilder|\Doctrine\ORM\QueryBuilder $query
     * @return object|array|void
     */
    public function get($query, string $table, array $context);
}
