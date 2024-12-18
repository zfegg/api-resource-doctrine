<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\ORM;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToManyAssociationMapping;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Zfegg\ApiRestfulHandler\ResourceInterface;
use Zfegg\ApiRestfulHandler\ResourceNotAllowedTrait;
use Zfegg\PsrMvc\Exception\ConflictHttpException;

class OrmResource implements ResourceInterface
{
    use ResourceNotAllowedTrait;

    public const ROOT_ALIAS = '__QUERY_ROOT_ALIAS__';

    private ?AssociationMapping $parentMapping = null;

    private array $creationContext;
    private array $mutationContext;

    /**
     * ORM resource constructor.
     * @param \Zfegg\ApiResourceDoctrine\Extension\ExtensionInterface[] $extensions
     */
    public function __construct(
        private EntityManagerInterface $em,
        private string $entityName,
        private SerializerInterface $serializer,
        array $denormalizationContext = [],
        private array $extensions = [],
        private ?OrmResource $parent = null,
        private ?string $parentContextKey = null
    ) {
        $this->creationContext = ($denormalizationContext['creation'] ?? []) + $denormalizationContext;
        $this->mutationContext = ($denormalizationContext['mutation'] ?? []) + $denormalizationContext;
        unset(
            $this->creationContext['creation'],
            $this->creationContext['mutation'],
            $this->mutationContext['creation'],
            $this->mutationContext['mutation'],
        );
    }

    public function getList(array $context = []): iterable
    {
        $tableAlias = 'o';
        $query = $this->em->createQueryBuilder();
        $query->select($tableAlias);
        $query->from($this->entityName, $tableAlias);
        $result = null;

        $this->joinParent($query, $context);

        $context[self::ROOT_ALIAS] = $tableAlias;

        foreach ($this->extensions as $extension) {
            $curResult = $extension->getList($query, $this->entityName, $context);
            if (is_iterable($curResult)) {
                $result = $curResult;
            }
        }

        if (is_iterable($result)) {
            return $result;
        }

        return $query->getQuery()->toIterable();
    }

    /**
     * @inheritdoc
     */
    public function delete(int|string $id, array $context = []): void
    {
        $obj = $this->em->find($this->entityName, $id);
        $this->em->remove($obj);
        $this->em->flush();
    }

    private function addFieldsByContext(array $context, array &$data): void
    {
        if (isset($context['add_fields'])) {
            foreach ($context['add_fields'] as $setFieldKey => $fromContextKey) {
                if (is_int($setFieldKey)) {
                    $setFieldKey = $fromContextKey;
                }

                $data[$setFieldKey] = $context[$fromContextKey];
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function create(object|array $data, array $context = []): object|array
    {
        $context[self::ROOT_ALIAS] = 'o';

        if ($this->parent) {
            $mapping = $this->getParentMapping();
            $key = $this->getParentContextKey();
            if ($mapping instanceof ManyToManyAssociationMapping) {
                $data[$mapping->fieldName] = (array)$context[$key];
            } else {
                $data[$mapping->fieldName] = $context[$key];
            }
        }

        $context = $context + $this->creationContext;
        $format = $context['format'] ?? 'json';

        $this->addFieldsByContext($context, $data);

        $meta = $this->getMetadata();
        foreach ($meta->getAssociationMappings() as $fieldName => $mapping) {
            if (property_exists($mapping, 'joinColumns')) {
                $column = $mapping->joinColumns[0]->name;
                if (isset($data[$column]) &&
                    $fieldName != $column &&
                    (is_string($data[$column]) || is_numeric($data[$column]))) {
                    $data[$fieldName] = $data[$column];
                    unset($data[$column]);
                }
            }
        }

        $obj = $this->serializer->denormalize(
            $data,
            $meta->getName(),
            $format,
            $context,
        );

        try {
            $this->em->persist($obj);
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }
        $primary = current($meta->getIdentifierValues($obj));

        return $this->get($primary, $context);
    }

    /**
     * @inheritdoc
     */
    public function update(int|string $id, object|array $data, array $context = []): object|array
    {
        $obj = $this->em->find($this->entityName, $id);

        $meta = $this->getMetadata();
        $context = $context + $this->mutationContext;
        $format = $context['format'] ?? 'json';
        $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $obj;
        $context[AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE] = true;

        $this->addFieldsByContext($context, $data);

        $this->serializer->denormalize(
            $data,
            $meta->getName(),
            $format,
            $context,
        );

        $this->em->persist($obj);
        $this->em->flush();
        $primary = current($this->em->getClassMetadata($this->entityName)->getIdentifierValues($obj));

        return $this->get($primary, $context);
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
        $meta = $this->em->getClassMetadata($this->entityName);

        $tableAlias = 'o';
        $query = $this->em->createQueryBuilder();
        $query->select($tableAlias);
        $query->from($this->entityName, $tableAlias);
        $query->where(
            $query->expr()->eq(
                "$tableAlias.{$meta->identifier[0]}",
                ':id'
            )
        );
        $query->setParameter('id', $id);

        $this->joinParent($query, $context);

        $context[self::ROOT_ALIAS] = $tableAlias;

        $result = null;

        foreach ($this->extensions as $extension) {
            if ($curResult = $extension->get($query, $this->entityName, $context)) {
                $result = $curResult;
            }
        }

        if ($result) {
            return $result;
        }

        return $query->getQuery()->getOneOrNullResult();
    }

    private function joinParent(QueryBuilder $query, array $context): void
    {
        $parentResource = $this->parent;
        $curResource = $this;
        $tableAlias = 'o';
        $p = 0;
        $nextJoin = null;

        while ($parentResource) {
            $curMapping = $curResource->getParentMapping();
            $key = $curResource->getParentContextKey();
            $field = "$tableAlias.{$curMapping['fieldName']}";
            if ($curMapping instanceof ManyToManyAssociationMapping) {
                $query->andWhere($query->expr()->isMemberOf(":$key", $field));
            } else {
                $query->andWhere($query->expr()->eq("IDENTITY($field)", ":$key"));
            }
            $query->setParameter($key, $context[$key]);
            if ($p) {
                $query->join($nextJoin, $tableAlias);
            }

            $p++;
            $tableAlias = "p$p";
            $nextJoin = $field;
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

        if ($this->parentContextKey) {
            return $this->parentContextKey;
        }

        $parentMapping = $this->getParentMapping();
        if ($parentMapping instanceof ManyToManyAssociationMapping) {
            $this->parentContextKey = $parentMapping->joinTable->inverseJoinColumns[0]->name;
        } else {
            $this->parentContextKey = $parentMapping->joinColumns[0]->name;
        }

        return $this->parentContextKey;
    }

    private function getParentMapping(): ?AssociationMapping
    {
        if (! $this->parentMapping) {
            $mappings = $this->getMetadata()->getAssociationMappings();
            foreach ($mappings as $mapping) {
                if ($mapping->targetEntity == $this->parent->entityName) {
                    $this->parentMapping = $mapping;
                    return $mapping;
                }
            }
        }

        return $this->parentMapping;
    }

    private function getMetadata(): ClassMetadata
    {
        return $this->em->getClassMetadata($this->entityName);
    }
}
