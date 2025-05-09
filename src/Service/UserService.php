<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use App\DTO\User\RegisterDTO;
use App\Entity\User;
use App\Event\UserRegistredEvent;
use App\Repository\UserRepository;
use App\Traits\ExeptionTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\CacheInterface;

class UserService
{
    use ExeptionTrait;
    public const string GET_ALL_USERS = 'getAllUsers';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer,
        private readonly CacheInterface $cache,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function createUser(RegisterDTO $data): User
    {
        $user = $this->entityManager->getRepository(UserRepository::class)->findOneBy(['email' => $data['email']]);

        if (!$user) {
            throw new \Exception(UserService::USER_ALREADY_EXIST, Response::HTTP_NOT_FOUND);
        }

        $user = (new User())
            ->setFirstname($data->firstname)
            ->setLastname($data->lastname)
            ->setEmail($data->email)
            ->setPassword($this->passwordHasher->hashPassword($user, $data->password))
            ->setPostCode($data->postCode)
            ->setCity($data->city)
            ->setAddress($data->address)
            ->setPhone($data->phone)
        ;

        $this->entityManager->persist($user);

        $event = new UserRegistredEvent($user);

        $this->eventDispatcher->dispatch($event, UserRegistredEvent::NAME);

        return $user;
    }

    /**
     * @throws \Exception
     * @throws InvalidArgumentException
     */
    public function createUserFromRequest(RequestStack $request): JsonResponse
    {
        $registerDTO = $this->serializer->deserialize(
            $request->getCurrentRequest()->getContent(),
            RegisterDTO::class,
            'json'
        );

        $violations = $this->validator->validate($registerDTO);

        if (count($violations) > 0) {
            $error = [];
            foreach ($violations as $violation) {
                $error[] = $violation->getMessage();
            }

            return new JsonResponse($error);
        }

        $user = $this->createUser($registerDTO);

        $this->cache->delete(self::GET_ALL_USERS);

        return new JsonResponse($user);
    }

    public function normalizeUserObject(User $user): array
    {
        $formatter = new \IntlDateFormatter(
            'fr_FR',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE,
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
            'role' => $user->getRoles()[0],
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
