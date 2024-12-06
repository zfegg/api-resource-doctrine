<?php

declare(strict_types = 1);

namespace Zfegg\ApiResourceDoctrine\Serializer;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class DoctrineEntityDenormalizer implements DenormalizerInterface
{
    private array $entityMaps = [];

    public function __construct(
        private ManagerRegistry $doctrine
    ) {
    }

    /**
     * @inheritdoc
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return $this->doctrine->getManager($this->entityMaps[$type])->getReference($type, $data);
    }

    /**
     * @inheritdoc
     */
    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []): bool
    {
        if ((is_numeric($data) || is_string($data)) && (class_exists($type) || interface_exists($type))) {
            if (isset($this->entityMaps[$type])) {
                return true;
            }
            if (isset($context['entity_manager'])) {
                $this->entityMaps[$type] = $context['entity_manager'];
                return true;
            }

            foreach ($this->doctrine->getManagerNames() as $name => $id) {
                $manager = $this->doctrine->getManager($name);
                $factory = $manager->getMetadataFactory();
                $factory->getAllMetadata();
                if ($factory->hasMetadataFor($type)) {
                    $this->entityMaps[$type] = $name;
                    return true;
                }
            }
        }

        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            'object' => true,
        ];
    }
}
