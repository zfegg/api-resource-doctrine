<?php declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Symfony\Component\Serializer\Serializer;
use Zfegg\ApiRestfulHandler\ResourceInterface;
use Zfegg\ApiRestfulHandler\ResourceNotAllowedTrait;

class DbalResource implements ResourceInterface
{
    use ResourceNotAllowedTrait;

    /**
     * @var \Zfegg\ApiResourceDoctrine\Extension\ExtensionInterface[]
     */
    private array $extensions;

    private string $table;

    private Connection $conn;
    private string $primary;

    private ?ResourceInterface $parent;
    private ?string $parentContextKey;
    private ?Serializer $serializer;

    /**
     * Dbal resource constructor.
     * @param \Zfegg\ApiResourceDoctrine\Extension\ExtensionInterface[] $extensions
     */
    public function __construct(
        Connection $connection,
        string $table,
        string $primary = 'id',
        array $extensions = [],
        ?ResourceInterface $parent = null,
        ?string $parentContextKey = null,
        private array $types = []
    ) {
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
        $query->select('*')->from($table, 'o');

        $this->joinParent($query, $context);

        $result = null;

        foreach ($this->extensions as $extension) {
            $curResult = $extension->getList($query, $this->table, $context);
            if (is_iterable($curResult)) {
                $result = $curResult;
            }
        }

        if (is_iterable($result)) {
            return $result;
        }

        $result = $query->executeQuery();

        return $this->convertRows($result);
    }

    private function convertRows(Result $result): iterable
    {
        foreach ($result->iterateAssociative() as $row) {
            yield $this->convertTypes($row);
        }
    }

    private function convertTypes(array $row): array
    {
        foreach ($this->types as $key => $type) {
            if (isset($row[$key])) {
                $row[$key] = $this->conn->convertToPHPValue($row[$key], $type);
            }
        }
        return $row;
    }

    /**
     * @inheritdoc
     */
    public function delete(int|string $id, array $context = []): void
    {
        $conn = $this->conn;
        $table = $conn->quoteIdentifier($this->table);
        $primary = $conn->quoteIdentifier($this->primary);
        $criteria = [$primary => $id];

        $conn->delete($table, $criteria);
    }

    /**
     * @inheritdoc
     */
    public function create(object|array $data, array $context = []): object|array
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

        if ($this->parent) {
            $key = $this->getParentContextKey();

            $qb->setValue($conn->quoteIdentifier($key), '?');
            $params[] = $context[$key];
        }

        $qb->setParameters($params, $this->types);
        $qb->executeStatement();

        return $this->get($conn->lastInsertId(), $context);
    }

    /**
     * @inheritdoc
     */
    public function update(int|string $id, object|array $data, array $context = []): object|array
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
        $this->joinParent($qb, $context);

        $qb->setParameters($values, $this->types);
        $qb->executeStatement();

        return $this->get($id, $context);
    }

    /**
     * @inheritdoc
     */
    public function patch(int|string $id, object|array $data, array $context = []): object|array
    {
        return $this->update($id, $data, $context);
    }

    /**
     * @inheritdoc
     */
    public function get(int|string $id, array $context = []): array|object|null
    {
        $conn = $this->conn;
        $table = $conn->quoteIdentifier($this->table);
        $primary = $conn->quoteIdentifier($this->primary);

        $query = $this->conn->createQueryBuilder();
        $query->select('*')
            ->from($table, 'o')
            ->where(
                $query->expr()->eq($primary, ":primary")
            )
        ;
        $query->setParameter('primary', $id);

        $this->joinParent($query, $context);

        $result = null;

        foreach ($this->extensions as $extension) {
            if ($curResult = $extension->get($query, $table, $context)) {
                $result = $curResult;
            }
        }

        if ($result) {
            return $result;
        }

        if ($result = $query->executeQuery()->fetchAssociative()) {
            return $this->convertTypes($result);
        }

        return null;
    }


    private function joinParent(QueryBuilder $query, array $context): void
    {
        $parentResource = $this->parent;
        $curResource = $this;
        $tableAlias = 'o';
        $p = 0;
        $join = null;

        while ($parentResource) {
            $key = $curResource->getParentContextKey();
            $field = "$tableAlias.$key";
            $query->andWhere($query->expr()->eq($this->conn->quoteIdentifier($field), ":$key"));
            $query->setParameter($key, $context[$key]);

            if ($p) {
                $joinTableAlias = "p$p";
                $joinField = "$joinTableAlias.{$curResource->primary}";
                $query->join($tableAlias, $join, $joinTableAlias, "$field = $joinField");
            }

            $p++;
            $join = $parentResource->table;
            $curResource = $parentResource;
            $parentResource = $parentResource->parent;
        }
    }

    public function getParent(): ?ResourceInterface
    {
        return $this->parent;
    }


    public function getParentContextKey(): ?string
    {
        if (! $this->parent) {
            return null;
        }

        return $this->parentContextKey;
    }
}
