<?php


namespace Zfegg\ApiResourceDoctrine\Extension;

use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;

class DefaultQueryExtension implements ExtensionInterface
{
    private array $parts;
    private array $entityParts;

    public function __construct(array $parts = [], array $entityParts = [])
    {
        $this->parts = $parts;
        $this->entityParts = $entityParts;
    }

    /**
     * @inheritDoc
     */
    public function getList($query, string $table, array $context)
    {
        foreach ($this->parts as [$partName, $part]) {
            $part = (array) $part;
            $query->{$partName}(...$part);
        };
    }

    public function get($query, string $table, array $context)
    {
        foreach ($this->entityParts as [$partName, $part]) {
            $part = (array) $part;
            $query->{$partName}(...$part);
        };
    }
}
