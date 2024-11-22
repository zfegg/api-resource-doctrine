<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Dbal;

use Doctrine\DBAL\Statement;

class BaseResult implements \IteratorAggregate
{
    private Statement $stmt;

    public function __construct(Statement $stmt)
    {
        $this->stmt = $stmt;
    }

    public function getIterator(): \Traversable
    {
        $result = $this->stmt->executeQuery();
        while ($row = $result->fetchAssociative()) {
            yield $row;
        }
    }
}
