<?php


namespace Zfegg\ApiResourceDoctrine\Dbal;

use Doctrine\DBAL\Connection;
use Zfegg\ApiResourceDoctrine\Extension\ExtensionInterface;
use Zfegg\ApiResourceDoctrine\ORM\OrmResource;
use Zfegg\ApiRestfulHandler\Resource\ResourceInterface;
use Zfegg\ApiRestfulHandler\Resource\ResourceNotAllowedTrait;

class DbalResource implements ResourceInterface
{
    use ResourceNotAllowedTrait;

    /**
     * @var ExtensionInterface[]
     */
    private array $extensions;

    private string $table;

    private Connection $conn;
    private string $primary;

    /**
     * Dbal resource constructor.
     * @param ExtensionInterface[] $extensions
     */
    public function __construct(Connection $connection, string $table, string $primary, array $extensions = [])
    {
        $this->conn = $connection;
        $this->table = $connection->quoteIdentifier($table);
        $this->primary = $connection->quoteIdentifier($primary);
        $this->extensions = $extensions;
    }

    public function getList(array $context = []): iterable
    {
        $query = $this->conn->createQueryBuilder();
        $query->select('*')->from($this->table);

        $result = null;

        foreach ($this->extensions as $extension) {
            if ($curResult = $extension->getList($query, $this->table, $context)) {
                $result = $curResult;
            }
        }

        if (is_iterable($result)) {
            return $result;
        }

        $stmt = $query->execute();

        return new BaseResult($stmt);
    }

    public function delete($id, array $context = []): void
    {
        $this->conn->delete($this->table, [$this->primary => $id]);
    }

    public function create($data, array $context = [])
    {
        $conn = $this->conn;

        $qb = $conn->createQueryBuilder();
        $qb->insert($this->table);

        $params = [];
        foreach ($data as $key => $value) {
            $qb->setValue($conn->quoteIdentifier($key), '?');
            $params[] = $value;
        }

        $qb->setParameters($params);
        $qb->execute();

        return $this->get($conn->lastInsertId(), $context);
    }

    public function update($id, $data, array $context = [])
    {
        $conn = $this->conn;

        $values = [];

        $qb = $conn->createQueryBuilder();

        foreach ($data as $key => $value) {
            $qb->set($conn->quoteIdentifier($key), '?');
            $values[] = $value;
        }

        $qb->update($this->table)
            ->where(
                $qb->expr()->eq($this->primary, $id)
            );
        $qb->setParameters($values);
        $qb->execute();

        return $this->get($id);
    }

    public function patch($id, $data, array $context = [])
    {
        return $this->update($id, $data, $context);
    }

    public function get($id, array $context = [])
    {
        $query = $this->conn->createQueryBuilder();
        $query->select('*')
            ->from($this->table)
            ->where(
                $query->expr()->eq($this->primary, $id)
            )
        ;

        $result = null;

        foreach ($this->extensions as $extension) {
            if ($curResult = $extension->get($query, $this->table, $context)) {
                $result = $curResult;
            }
        }

        if ($result) {
            return $result;
        }

        return $query->execute()->fetchAssociative();
    }
}
