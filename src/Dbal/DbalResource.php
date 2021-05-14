<?php


namespace Zfegg\ApiResourceDoctrine\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Zfegg\ApiResourceDoctrine\Extension\ExtensionInterface;
use Zfegg\ApiResourceDoctrine\ORM\OrmResource;
use Zfegg\ApiRestfulHandler\Middleware\ResourceFindMiddleware;
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

    private ?ResourceInterface $parent;
    private ?string $parentContextKey;

    /**
     * Dbal resource constructor.
     * @param ExtensionInterface[] $extensions
     */
    public function __construct(
        Connection $connection,
        string $table,
        string $primary,
        array $extensions = [],
        ?ResourceInterface $parent = null,
        ?string $parentContextKey = null,
    )
    {
        $this->conn = $connection;
        $this->table = $table;
        $this->primary = $primary;
        $this->extensions = $extensions;
        $this->parent = $parent;
        $this->parentContextKey = $parentContextKey;
    }

    public function getList(array $context = []): iterable
    {
        $conn = $this->conn;
        $table = $conn->quoteIdentifier($this->table);
        $query = $conn->createQueryBuilder();
        $query->select('*')->from($table);

        $this->queryAddParent($query, $context);

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
        $conn = $this->conn;
        $table = $conn->quoteIdentifier($this->table);
        $primary = $conn->quoteIdentifier($this->primary);
        $criteria = [$primary => $id];

        if (isset($context[self::PARENT_RESOURCE])) {
            $attr = $context[self::PARENT_RESOURCE];
            if (isset($context[$attr])) {
                $criteria[$conn->quoteIdentifier($attr)] = $context[$attr];
            }
        }

        $conn->delete($table, $criteria);
    }

    public function create($data, array $context = [])
    {
        $conn = $this->conn;
        $table = $conn->quoteIdentifier($this->table);

        $qb = $conn->createQueryBuilder();
        $qb->insert($table);

        $params = [];
        foreach ($data as $key => $value) {
            $qb->setValue($conn->quoteIdentifier($key), '?');
            $params[] = $value;
        }

        if (isset($context[self::PARENT_RESOURCE])) {
            $attr = $context[self::PARENT_RESOURCE];
            if (isset($context[$attr])) {
                $qb->setValue($conn->quoteIdentifier($attr), '?');
                $params[] = $context[$attr];
            }
        }

        $qb->setParameters($params);
        $qb->execute();

        return $this->get($conn->lastInsertId(), $context);
    }

    public function update($id, $data, array $context = [])
    {
        $conn = $this->conn;
        $table = $conn->quoteIdentifier($this->table);
        $primary = $conn->quoteIdentifier($this->primary);

        $values = [];

        $qb = $conn->createQueryBuilder();

        foreach ($data as $key => $value) {
            $qb->set($conn->quoteIdentifier($key), '?');
            $values[] = $value;
        }

        $qb->update($table)
            ->where(
                $qb->expr()->eq($primary, $id)
            );
        $this->queryAddParent($qb, $context);

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
        $conn = $this->conn;
        $table = $conn->quoteIdentifier($this->table);
        $primary = $conn->quoteIdentifier($this->primary);

        $query = $this->conn->createQueryBuilder();
        $query->select('*')
            ->from($table)
            ->where(
                $query->expr()->eq($primary, ":primary")
            )
        ;
        $query->setParameter('primary', $id);

        $this->queryAddParent($query, $context);

        $result = null;

        foreach ($this->extensions as $extension) {
            if ($curResult = $extension->get($query, $table, $context)) {
                $result = $curResult;
            }
        }

        if ($result) {
            return $result;
        }

        return $query->execute()->fetchAssociative();
    }

    private function queryAddParent(QueryBuilder $query, array $context)
    {
        if ($this->parent && isset($context[$this->parentContextKey])) {
            $query->andWhere($query->expr()->eq(
                $this->conn->quoteIdentifier($this->parentContextKey),
                ":$this->parentContextKey"
            ));
            $query->setParameter($this->parentContextKey, $context[$this->parentContextKey]);
        }
    }

    public function getParent(): ?ResourceInterface
    {
        return $this->parent;
    }


    public function getParentContextKey(): ?string
    {
        if (!$this->parent) {
            return null;
        }

        return $this->parentContextKey;
    }
}
