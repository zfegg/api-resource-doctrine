<?php

namespace Zfegg\ApiResourceDoctrine\ORM;

use Doctrine\ORM\EntityManager;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Zfegg\ApiResourceDoctrine\Extension\ExtensionInterface;
use Zfegg\ApiRestfulHandler\Resource\ResourceInterface;
use Zfegg\ApiRestfulHandler\Resource\ResourceNotAllowedTrait;

class OrmResource implements ResourceInterface
{
    use ResourceNotAllowedTrait;

    public const ROOT_ALIAS = '__QUERY_ROOT_ALIAS__';

    private EntityManager $em;
    private string $entityName;
    /**
     * @var ExtensionInterface[]
     */
    private array $extensions;

    private SerializerInterface $serializer;
    /**
     * @var PropertyAccessorInterface|null
     */
    private ?PropertyAccessorInterface $propertyAccessor;

    /**
     * ORM resource constructor.
     * @param ExtensionInterface[] $extensions
     */
    public function __construct(EntityManager $em,
                                string $entityName,
                                SerializerInterface $serializer,
                                array $extensions = [],
                                PropertyAccessorInterface $propertyAccessor = null
    )
    {
        $this->extensions = $extensions;
        $this->em = $em;
        $this->entityName = $entityName;
        $this->serializer = $serializer;
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    public function getList(array $context = []): iterable
    {
        $query = $this->em->createQueryBuilder();
        $query->select('o');
        $query->from($this->entityName, 'o');
        $result = null;

        $context[self::ROOT_ALIAS] = 'o';

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
        $obj = $this->serializer->denormalize($data, $this->entityName, $format, $context);

        $this->em->persist($obj);
        $this->em->flush();
        $this->em->refresh($obj);

        return $obj;
    }

    public function update($id, $data, array $context = [])
    {
        $format = $context['format'] ?? '';
        $obj = $this->em->find($this->entityName, $id);
        $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $obj;
        $context[AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE] = true;

        $this->serializer->denormalize($data, $this->entityName, $format, $context);

        $this->em->persist($obj);
        $this->em->flush();
        $this->em->refresh($obj);

        return $obj;
    }

    public function patch($id, $data, array $context = [])
    {
        return $this->update($id, $data, $context);
    }

    public function get($id, array $context = [])
    {
        return $this->em->find($this->entityName, $id);
    }
}
