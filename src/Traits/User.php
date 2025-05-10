<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Traits;

use Doctrine\ORM\Mapping\Id;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;

trait User
{
    #[Id]
    public ?int $id;

    #[Email]
    public ?string $email;

    public array $roles;

    #[Length(
        min: 1,
        max: 255,
        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractère.',
        maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.'
    )]
    public ?string $password;

    #[Assert\Uuid(message: 'L\'uuid n\'est pas valide')]
    public ?string $uuid;

    #[Length(
        max: 255,
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    public ?string $firstname;

    #[Length(
        max: 255,
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    public ?string $lastname;
    #[Length(
        max: 255,
        maxMessage: 'Le nom de la company ne peut pas dépasser {{ limit }} caractères.'
    )]
    public ?string $companyName;

    #[Assert\DateTime]
    public ?\DateTimeImmutable $createdAt;

    #[Assert\DateTime]
    public ?\DateTimeImmutable $updatedAt;

    #[Length(
        min: 8,
        max: 20,
        minMessage: 'Le numéro de téléphone doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères.'
    )]
    public ?string $phone;

    #[Length(
        min: 1,
        max: 255,
        minMessage: "L'adresse doit contenir au moins {{ limit }} caractère.",
        maxMessage: "L'adresse ne peut pas dépasser {{ limit }} caractères."
    )]
    public ?string $address;

    #[Length(
        min: 1,
        max: 255,
        minMessage: 'La ville doit contenir au moins {{ limit }} caractère.',
        maxMessage: 'La ville ne peut pas dépasser {{ limit }} caractères.'
    )]
    public ?string $city;

    #[Length(
        min: 1,
        max: 10,
        minMessage: 'Le code postal doit contenir au moins {{ limit }} caractère.',
        maxMessage: 'Le code postal ne peut pas dépasser {{ limit }} caractères.'
    )]
    public ?string $postCode;

    #[Length(
        min: 1,
        max: 100,
        minMessage: 'Le pays doit contenir au moins {{ limit }} caractère.',
        maxMessage: 'Le pays ne peut pas dépasser {{ limit }} caractères.'
    )]
    public ?string $country;

    public static function fromArray(array $data): self
    {
        if (empty($data)) {
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
