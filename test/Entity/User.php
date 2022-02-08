<?php declare(strict_types = 1);

namespace ZfeggTest\ApiResourceDoctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table("users")]
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "name", columns: ["name"])]
class User
{
    #[ORM\Column("id", "integer")]
    #[ORM\Id]
    #[ORM\GeneratedValue("IDENTITY")]
    private int $id;

    #[ORM\Column("name", "string", 255)]
    private string $name;

    #[ORM\Column("status", "integer")]
    private int $status = 1;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }
}
