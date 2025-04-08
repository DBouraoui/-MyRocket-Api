<?php

namespace App\traits;

use Doctrine\DBAL\Types\Types;

trait Contact {
    public ?int $id = null;
    public ?string $uuid = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $email = null;
    public ?string $firstname = null;
    public ?string $lastname = null;
    public ?string $companyName = null;
    public array $tags = [];
    public ?\DateTimeImmutable $createdAt = null;
    public array $pictures = [];

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
}