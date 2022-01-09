<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Extension;

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

    /**
     * @inheritdoc
     */
    public function get($query, string $table, array $context): void
    {
        foreach ($this->entityParts as [$partName, $part]) {
            $part = (array) $part;
            $query->{$partName}(...$part);
        };
    }
}
