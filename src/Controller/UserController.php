<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('api/user', name: 'user',)]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly ValidatorInterface $validator,
        private readonly UserRepository $userRepository,
    ) {

    }

    #[Route( '/register', name: 'user_register', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $constraints = new Collection([
                'firstname' => [new NotBlank()],
                'lastname' => [new NotBlank()],
                'email' => [new NotBlank(), new Email()],
                'password' => [new NotBlank()],
                'postcode' => [new NotBlank()],
                'city' => [new NotBlank()],
                'country' => [new NotBlank()],
                'address' => [new NotBlank()],
                'phone' => [new NotBlank()]
            ]);

            $violations = $this->validator->validate($data, $constraints);

            if (count($violations) > 0) {
                $error = [];
                foreach ($violations as $violation) {
                    $error[] = $violation->getMessage();
                }
                return $this->json($error, Response::HTTP_BAD_REQUEST);
            }

            $user = new User();
            $user->setFirstname($data['firstname']);
            $user->setLastname($data['lastname']);
            $user->setEmail($data['email']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            $user->setPostCode($data['postcode']);
            $user->setCity($data['city']);
            $user->setCountry($data['country']);
            $user->setAddress($data['address']);
            $user->setPhone($data['phone']);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->json(['success'=>true], Response::HTTP_CREATED);

        } catch ( \Exception $e ) {
            $this->logger->error("Error creating user", ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);        }
    }


    #[Route(methods: ['GET'])]
    public function get(Request $request) {
        try {
            $criteria = $request->query->get('criteria');
            $all = $request->query->get('all');

            if (!empty($criteria) && empty($all)) {
                $userFinding = $this->userRepository->getOneUserByCriteria($criteria);
                return $this->json($this->normalizeUserObject($userFinding), Response::HTTP_OK);
            }

            if (!empty($all) && empty($criteria)) {
                $userFinding = $this->userRepository->findAll();
                return $this->json($this->normalizeUsersObjects($userFinding), Response::HTTP_OK);
            }

            return $this->json(['message' => 'No valid criteria provided'], Response::HTTP_BAD_REQUEST);

        } catch(\Exception $e) {
            $this->logger->error("Error retrieving users", ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(methods: ['PUT'])]
    public function put(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $customer = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);
            if (!$customer) {
                return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
            }

            unset($data['uuid']);

            $allowedFields = ['email', 'firstname', 'lastname', 'phone', 'address', 'city', 'postCode', 'country'];

            foreach ($data as $field => $value) {
                if (!in_array($field, $allowedFields)) {
                    unset($data[$field]);
                     return $this->json(['error' => 'Field not allowed: ' . $field], Response::HTTP_BAD_REQUEST);
                }
            }

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $setter = 'set' . ucfirst($field);
                    if (method_exists($customer, $setter)) {
                        $customer->$setter($data[$field]);
                    }
                }
            }

            $this->entityManager->flush();

            return $this->json(
                ['message' => 'User updated successfully'],
                Response::HTTP_OK
            );

        } catch(\Exception $e) {
            $this->logger->error("Error updating user", ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(methods: ['DELETE'])]
    public function delete(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $customer = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

            $this->entityManager->remove($customer);
            $this->entityManager->flush();

            return $this->json(['message' => 'User deleted successfully'], Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error("Error deleting user", ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function normalizeUserObject(User $user): array
    {
        return [
            'uuid' => $user->getUuid(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'email' => $user->getEmail(),
            'postcode' => $user->getPostCode(),
            'city' => $user->getCity(),
            'country' => $user->getCountry(),
            'address' => $user->getAddress(),
            'phone' => $user->getPhone(),
            'role'=> $user->getRoles()[0]
        ];
    }

    private function normalizeUsersObjects(array $users): array
    {
        $usersObject = [];

        foreach ($users as $user) {
            $usersObject[] = $this->normalizeUserObject($user);
        }

        return $usersObject;
    }
}
