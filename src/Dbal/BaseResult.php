<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Dbal;

use Doctrine\DBAL\Driver\Statement;

class BaseResult implements \IteratorAggregate
{
    private Statement $stmt;

    public function __construct(Statement $stmt)
    {
        $this->stmt = $stmt;
    }

    /**
     * @inheritdoc
     */
    public function getIterator()
    {
        while ($row = $this->stmt->fetchAssociative()) {
            yield $row;
        }
    }
}
