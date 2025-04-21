<?php

namespace App\service;

use App\DTO\user\RegisterDTO;
use App\Entity\User;
use App\traits\ExeptionTrait;
use Doctrine\ORM\EntityManagerInterface;
use IntlDateFormatter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    use ExeptionTrait;

    public function __construct
    (
    private readonly UserPasswordHasherInterface $passwordHasher,
    private readonly EntityManagerInterface $entityManager,
    ) {

    }

    public function createUser(RegisterDTO $data): User {
        try {

            $user = new User();
            $data['firstname'] ? $user->setFirstname($data['firstname']) : null;
            $data['lastname'] ? $user->setLastname($data['lastname']) : null;
            $data['companyName'] ? $user->setCompanyName($data['companyName']) : null;
            $user->setEmail($data['email']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            $user->setPostCode($data['postCode']);
            $user->setCity($data['city']);
            $user->setCountry($data['country']);
            $user->setAddress($data['address']);
            $user->setPhone($data['phone']);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $user;
        } catch( \Exception $e ) {
            Throw new \Exception($e->getMessage(),$e->getCode() || Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function normalizeUserObject(User $user): array
    {
        $formatter = new \IntlDateFormatter(
            'fr_FR',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            null,
            null,
            'EEEE d MMMM YYYY'
        );

        return [
            'uuid' => $user->getUuid(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'companyName' => $user->getCompanyName(),
            'email' => $user->getEmail(),
            'postCode' => $user->getPostCode(),
            'city' => $user->getCity(),
            'country' => $user->getCountry(),
            'address' => $user->getAddress(),
            'phone' => $user->getPhone(),
            'createdAt' => $formatter->format($user->getCreatedAt()),
            'updatedAt' => $formatter->format($user->getUpdatedAt()),
            'role'=> $user->getRoles()[0]
        ];
    }

    public function normalizeUsersObjects(array $users): array
    {
        $usersObject = [];

        foreach ($users as $user) {
            $usersObject[] = $this->normalizeUserObject($user);
        }

        return $usersObject;
    }
}