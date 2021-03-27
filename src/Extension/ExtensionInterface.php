<?php


namespace Zfegg\ApiResourceDoctrine\Extension;


use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;

interface ExtensionInterface
{

    /**
     * @param ORMQueryBuilder|DbalQueryBuilder  $query
     * @return iterable|void
     */
    public function getList($query, string $table, array $context);

    /**
     * @param ORMQueryBuilder|DbalQueryBuilder  $query
     * @return object|array|void
     */
    public function get($query, string $table, array $context);
}
