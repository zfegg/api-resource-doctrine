<?php


namespace Zfegg\ApiResourceDoctrine\Dbal;


use Doctrine\DBAL\Driver\Statement;
use Exception;
use Traversable;

class BaseResult implements \IteratorAggregate
{
    private Statement $stmt;

    public function __construct(Statement $stmt)
    {
        $this->stmt = $stmt;
    }

    public function getIterator()
    {
        while ($row = $this->stmt->fetchAssociative()) {
            yield $row;
        }
    }
}