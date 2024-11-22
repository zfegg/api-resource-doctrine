<?php declare(strict_types = 1);

namespace ZfeggTest\ApiResourceDoctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table("groups")]
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "name", columns: ["name"])]
class Group
{

    #[ORM\Column("id", "integer")]
    #[ORM\Id]
    #[ORM\GeneratedValue("IDENTITY")]
    private int $id;

    #[ORM\Column("name", "string", 255)]
    private string $name;

    #[ORM\OneToMany(targetEntity: User::class, mappedBy: "group", cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

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

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function setUsers(Collection $users): void
    {
        $this->users = $users;
    }
}
