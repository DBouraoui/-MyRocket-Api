<?php

namespace App\traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Id;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Uuid;

trait Contact {
    #[Id]
    public ?int $id;

    #[Uuid(message: "L'UUID n'est pas valide.")]
    public ?string $uuid;

    #[Length(
        min: 1,
        max: 255,
        minMessage: "Le titre doit contenir au moins {{ limit }} caractère.",
        maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères."
    )]
    public ?string $title;

    #[Length(
        min: 1,
        max: 255,
        minMessage: "La description doit contenir au moins {{ limit }} caractère.",
        maxMessage: "La description ne peut pas dépasser {{ limit }} caractères."
    )]
    public ?string $description;

    #[Length(
        min: 1,
        max: 255,
        minMessage: "L'email doit contenir au moins {{ limit }} caractère.",
        maxMessage: "L'email ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Email(message: "L'adresse email '{{ value }}' n'est pas valide.")]
    public ?string $email;

    #[Length(
        min: 1,
        max: 255,
        minMessage: "Le prénom doit contenir au moins {{ limit }} caractère.",
        maxMessage: "Le prénom ne peut pas dépasser {{ limit }} caractères."
    )]
    public ?string $firstname;

    #[Length(
        min: 1,
        max: 255,
        minMessage: "Le nom doit contenir au moins {{ limit }} caractère.",
        maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères."
    )]
    public ?string $lastname;

    #[Length(
        min: 1,
        max: 255,
        minMessage: "Le nom de l'entreprise doit contenir au moins {{ limit }} caractère.",
        maxMessage: "Le nom de l'entreprise ne peut pas dépasser {{ limit }} caractères."
    )]
    public ?string $companyName;

    public array $tags;

    #[DateTime(message: "La date n'est pas valide.")]
    public ?\DateTimeImmutable $createdAt;

    public array $pictures;

    public function normalize(\App\Entity\User $user): array {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'email' => $this->email,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'companyName' => $this->companyName,
            'tags' => $this->tags,
            'pictures' => $this->pictures,
            'createdAt' => $this->createdAt,
        ];
    }

    public function normalizeArray(array $array): array {
        $contactsArray = [];

        foreach ($array as $contact) {
            $contactsArray[] = $this->normalize($contact);
        }

        return $contactsArray;
    }

    public static function fromArray(array $data): self
    {
        if(empty($data)) {
            throw new \Exception('The array is empty');
        }

        $self = new self();

        foreach ($data as $key => $value) {
            if (property_exists($self, $key)) {
                $self->{$key} = $value;
            }
        }
        return $self;
    }
}