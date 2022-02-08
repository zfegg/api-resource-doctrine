<?php declare(strict_types = 1);

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
    public function denormalize($data, string $type, ?string $format = null, array $context = [])
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
            foreach ($this->doctrine->getManagerNames() as $name => $id) {
                $manager = $this->doctrine->getManager($name);

                if ($manager->getClassMetadata($type)) {
                    $this->entityMaps[$type] = $name;
                    return true;
                }
            }
        }

        return false;
    }
}
