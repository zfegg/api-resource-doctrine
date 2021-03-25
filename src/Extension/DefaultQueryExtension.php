<?php


namespace Zfegg\ApiResourceDoctrine\Extension;

class DefaultQueryExtension implements ExtensionInterface
{
    private array $parts;

    public function __construct(array $parts)
    {
        $this->parts = $parts;
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
}
