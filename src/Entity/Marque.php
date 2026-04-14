<?php
namespace App\Entity;

use App\Repository\MarqueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MarqueRepository::class)]
class Marque
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length:10)]
    private ?bool $hasPhone = false;

    #[ORM\Column(length:10)]
    private ?bool $hasTablet = false;

    #[ORM\OneToMany(targetEntity: Model::class, mappedBy: 'marque')]
    private Collection $models;

    public function __construct()
    {
        $this->models = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function hasPhone(): bool
    {
        return $this->hasPhone;
    }

    public function setHasPhone(bool $hasPhone): self
    {
        $this->hasPhone = $hasPhone;

        return $this;
    }

    public function hasTablet(): bool
    {
        return $this->hasTablet;
    }

    public function setHasTablet(bool $hasTablet): self
    {
        $this->hasTablet = $hasTablet;

            return $this;
        }

    /**
     * @return Collection|Model[]
     */
    public function getModels(): Collection
    {
        return $this->models;
    }

    public function addModel(Model $model): self
    {
        if (!$this->models->contains($model)) {
            $this->models[] = $model;
            $model->setMarque($this);
        }

        return $this;
    }

    public function removeModel(Model $model): self
    {
        if ($this->models->removeElement($model)) {
            // set the owning side to null (unless already changed)
            if ($model->getMarque() === $this) {
                $model->setMarque(null);
            }
        }

        return $this;
    }
}
