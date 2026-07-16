<?php

namespace App\Entity;

use App\Repository\HoraireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HoraireRepository::class)]
#[ORM\Table(name: 'horaire')]
class Horaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    private ?int $jourNumero = null;

    #[ORM\Column(length: 20)]
    private ?string $jourNom = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $matinOuverture = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $matinFermeture = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $apresmidiOuverture = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $apresmidiFermeture = null;

    #[ORM\Column]
    private bool $ferme = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJourNumero(): ?int
    {
        return $this->jourNumero;
    }

    public function setJourNumero(int $jourNumero): self
    {
        $this->jourNumero = $jourNumero;
        return $this;
    }

    public function getJourNom(): ?string
    {
        return $this->jourNom;
    }

    public function setJourNom(string $jourNom): self
    {
        $this->jourNom = $jourNom;
        return $this;
    }

    public function getMatinOuverture(): ?\DateTimeInterface
    {
        return $this->matinOuverture;
    }

    public function setMatinOuverture(?\DateTimeInterface $matinOuverture): self
    {
        $this->matinOuverture = $matinOuverture;
        return $this;
    }

    public function getMatinFermeture(): ?\DateTimeInterface
    {
        return $this->matinFermeture;
    }

    public function setMatinFermeture(?\DateTimeInterface $matinFermeture): self
    {
        $this->matinFermeture = $matinFermeture;
        return $this;
    }

    public function getApresmidiOuverture(): ?\DateTimeInterface
    {
        return $this->apresmidiOuverture;
    }

    public function setApresmidiOuverture(?\DateTimeInterface $apresmidiOuverture): self
    {
        $this->apresmidiOuverture = $apresmidiOuverture;
        return $this;
    }

    public function getApresmidiFermeture(): ?\DateTimeInterface
    {
        return $this->apresmidiFermeture;
    }

    public function setApresmidiFermeture(?\DateTimeInterface $apresmidiFermeture): self
    {
        $this->apresmidiFermeture = $apresmidiFermeture;
        return $this;
    }

    public function isFerme(): bool
    {
        return $this->ferme;
    }

    public function setFerme(bool $ferme): self
    {
        $this->ferme = $ferme;
        return $this;
    }
}
