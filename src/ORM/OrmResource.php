<?php

namespace Zfegg\ApiResourceDoctrine\ORM;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Zfegg\ApiResourceDoctrine\Extension\ExtensionInterface;
use Zfegg\ApiRestfulHandler\Exception\ApiProblem;
use Zfegg\ApiRestfulHandler\Exception\RequestException;
use Zfegg\ApiRestfulHandler\Resource\ResourceInterface;
use Zfegg\ApiRestfulHandler\Resource\ResourceNotAllowedTrait;

class OrmResource implements ResourceInterface
{
    use ResourceNotAllowedTrait;

    public const ROOT_ALIAS = '__QUERY_ROOT_ALIAS__';

    private EntityManager $em;
    private string $entityName;

    private ?OrmResource $parent;

    /**
     * @var ExtensionInterface[]
     */
    private array $extensions;

    private SerializerInterface $serializer;
    private ?string $parentContextKey = null;
    private ?array $associationMapping = null;
    private ?ClassMetadataInfo $metadata = null;
    private PropertyAccessorInterface $propertyAccessor;

    /**
     * ORM resource constructor.
     * @param ExtensionInterface[] $extensions
     */
    public function __construct(
        EntityManager $em,
        string $entityName,
        SerializerInterface $serializer,
        PropertyAccessorInterface $propertyAccessor = null,
        array $extensions = [],
        ?OrmResource $parent = null
    )
    {
        $this->extensions = $extensions;
        $this->em = $em;
        $this->entityName = $entityName;
        $this->serializer = $serializer;
        $this->parent = $parent;
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
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
            if ($curResult = $extension->getList($query, $this->entityName, $context)) {
                $result = $curResult;
            }
        }

        if (is_iterable($result)) {
            return $result;
        }

        return $query->getQuery()->getResult();
    }

    public function delete($id, array $context = []): void
    {
        $obj = $this->em->find($this->entityName, $id);
        $this->em->remove($obj);
        $this->em->flush();
    }

    public function create($data, array $context = [])
    {
        $format = $context['format'] ?? '';
        $context[self::ROOT_ALIAS] = 'o';

        $associationFields = [];

        if ($this->parent) {
            $mapping = $this->getAssociationMapping();
            $val = $context[$this->getParentContextKey()];
            $associationFields[$mapping['fieldName']] = $this->em->getReference($this->parent->entityName, $val);
        }

        $meta = $this->getMetadata();
        foreach ($meta->getAssociationMappings() as $fieldName => $mapping) {
            if (isset($mapping['joinColumns'])) {
                $column = $mapping["joinColumns"][0]["name"];
                if (isset($data[$column]) && (is_string($data[$column]) || is_numeric($data[$column]))) {
                    $associationFields[$fieldName] = $this->em->getReference($mapping['targetEntity'], $data[$column]);
                    unset($data[$column]);
                    continue;
                }
            }
        }

        $this->updateJoins($data);

        $obj = $this->serializer->denormalize($data, $this->entityName, $format, $context);

        foreach ($associationFields as $key => $val) {
            $this->propertyAccessor->setValue($obj, $key, $val);
        }

        $this->em->persist($obj);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new RequestException($e->getMessage(), 409, $e);
        }
        $primary = current($meta->getIdentifierValues($obj));

        return $this->get($primary, $context);
    }

    public function update($id, $data, array $context = [])
    {
        $format = $context['format'] ?? '';
        $obj = $this->em->find($this->entityName, $id);
        $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $obj;
        $context[AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE] = true;

        $this->updateJoins($data);
        $this->serializer->denormalize($data, $this->entityName, $format, $context);

        $this->em->persist($obj);
        $this->em->flush();
        $primary = current($this->em->getClassMetadata($this->entityName)->getIdentifierValues($obj));

        return $this->get($primary, $context);
    }

    public function patch($id, $data, array $context = [])
    {
        return $this->update($id, $data, $context);
    }

    public function get($id, array $context = [])
    {
        $meta = $this->em->getClassMetadata($this->entityName);

        $tableAlias = 'o';
        $query = $this->em->createQueryBuilder();
        $query->select($tableAlias);
        $query->from($this->entityName, $tableAlias);
        $query->where(
            $query->expr()->eq(
                "$tableAlias.{$meta->identifier[0]}",
                $id
            )
        );

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

        while ($parentResource) {
            $curMapping = $curResource->getAssociationMapping();
            $join = "$tableAlias.{$curMapping['fieldName']}";
            $key = $curResource->getParentContextKey();
            $query->andWhere($query->expr()->eq("IDENTITY($join)", ":$key"));
            $query->setParameter($key, $context[$curResource->getParentContextKey()]);
            if ($curResource !== $this) {
                $tableAlias = "p$p";
                $query->join($join, 'p');
            }

            $p++;
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
        if (!$this->parent) {
            return null;
        }

        if ($this->parentContextKey) {
            return $this->parentContextKey;
        }

        $this->parentContextKey = $this->getAssociationMapping()['joinColumns'][0]['name'];

        return $this->parentContextKey;
    }

    private function getAssociationMapping(): ?array
    {
        if (! $this->associationMapping) {
            $mappings = $this->getMetadata()
                ->getAssociationsByTargetClass($this->parent->entityName);
            $this->associationMapping = current($mappings);
        }

        return $this->associationMapping;
    }

    private function getMetadata(): ClassMetadataInfo
    {
        if (! $this->metadata) {
            $this->metadata = $this->em->getClassMetadata($this->entityName);
        }

        return $this->metadata;
    }

    private function updateJoins(array &$data):void
    {
        $meta = $this->getMetadata();
        foreach ($meta->getAssociationMappings() as $fieldName => $mapping) {
            $targetMeta = $this->em->getClassMetadata($mapping['targetEntity']);
            $id = $targetMeta->getIdentifier()[0];

            // ManyToMany Collection
            if (isset($data[$fieldName]) && is_array($data[$fieldName]) && isset($data[$fieldName][0])) {
                if ($meta->isCollectionValuedAssociation($fieldName)) {
                    $collection = new ArrayCollection();

                    foreach ($data[$fieldName] as $item) {
                        $ref = $this->em->getReference($mapping['targetEntity'], is_array($item) ? $item[$id]: $item);
                        $collection->add($ref);
                    }

                    $data[$fieldName] = $collection;
                    continue;
                }
            }
        }
    }
}
