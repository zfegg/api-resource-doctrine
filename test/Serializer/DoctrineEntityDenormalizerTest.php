<?php declare(strict_types = 1);

namespace ZfeggTest\ApiResourceDoctrine\Serializer;

use Zfegg\ApiResourceDoctrine\Serializer\DoctrineEntityDenormalizer;
use PHPUnit\Framework\TestCase;
use Zfegg\DoctrineHelper\ContainerManagerRegistry;
use ZfeggTest\ApiResourceDoctrine\Entity\User;
use ZfeggTest\ApiResourceDoctrine\SetUpContainerTrait;

class DoctrineEntityDenormalizerTest extends TestCase
{
    use SetUpContainerTrait;

    private array $config = [
    ];

    public function testDenormalize(): void
    {
        $denormalizer = new DoctrineEntityDenormalizer($this->container->get(ContainerManagerRegistry::class));

        $data = 1;
        $type = User::class;

        $this->assertTrue($denormalizer->supportsDenormalization($data, $type));
        $rs = $denormalizer->denormalize($data, $type);
        $this->assertInstanceOf(User::class, $rs);
        $this->assertEquals($data, $rs->getId());
    }
}
