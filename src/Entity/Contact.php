<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité du formulaire de contact (non persistée — utilisée uniquement
 * pour la validation et l'envoi par email).
 *
 * Les contraintes utilisent des attributs PHP 8 (recommandé en Symfony 7.x).
 */
class Contact
{
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 60,
        minMessage: 'Le prénom doit faire au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[\p{L}\p{M}\'\- ]+$/u',
        message: 'Le prénom ne peut contenir que des lettres, espaces, tirets ou apostrophes.'
    )]
    private ?string $firstName = null;

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 60,
        minMessage: 'Le nom doit faire au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[\p{L}\p{M}\'\- ]+$/u',
        message: 'Le nom ne peut contenir que des lettres, espaces, tirets ou apostrophes.'
    )]
    private ?string $lastName = null;

    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'adresse email "{{ value }}" n\'est pas valide.')]
    #[Assert\Length(
        max: 180,
        maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $email = null;

    /**
     * Téléphone optionnel. Accepte les formats usuels :
     * 06 12 34 56 78, 06.12.34.56.78, 06-12-34-56-78, 0612345678, +33 6 12 34 56 78, etc.
     */
    #[Assert\Length(
        max: 25,
        maxMessage: 'Le numéro de téléphone est trop long.'
    )]
    #[Assert\Regex(
        pattern: '/^(?:\+?[0-9][0-9 .\-]{8,20})?$/',
        message: 'Le numéro de téléphone n\'est pas valide.'
    )]
    private ?string $phone = null;

    #[Assert\NotBlank(message: 'Le message est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 4000,
        minMessage: 'Votre message doit faire au moins {{ limit }} caractères.',
        maxMessage: 'Votre message ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $message = null;

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName !== null ? trim($firstName) : null;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName !== null ? trim($lastName) : null;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email !== null ? trim(strtolower($email)) : null;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone !== null ? trim($phone) : null;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message !== null ? trim($message) : null;

        return $this;
    }
}
