<?php

namespace App\Controller;

use App\DTO\user\RegisterDTO;
use App\DTO\user\UserDeleteDTO;
use App\DTO\user\UserPutDTO;
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
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
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
    #[IsGranted('PUBLIC_ACCESS')]
    public function post(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $registerDTO = RegisterDTO::fromArray($data);

            $violations = $this->validator->validate($registerDTO);

            if (count($violations) > 0) {
                $error = [];
                foreach ($violations as $violation) {
                    $error[] = $violation->getMessage();
                }
                return $this->json($error, Response::HTTP_BAD_REQUEST);
            }

            if (!empty($this->userRepository->findOneBy(['email'=>$data['email']]))) {
                throw new \Exception('User already exists');
            }

            $user = new User();
            $user->setFirstname($data['firstname']);
            $user->setLastname($data['lastname']);
            $user->setEmail($data['email']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            $user->setPostCode($data['postCode']);
            $user->setCity($data['city']);
            $user->setCountry($data['country']);
            $user->setAddress($data['address']);
            $user->setPhone($data['phone']);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->json(['success'=>true], Response::HTTP_CREATED);

        } catch ( \Exception $e ) {
            $this->logger->error("Error creating user", ['error' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    #[Route(methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function get(Request $request): JsonResponse
    {
        try {
            $criteria = $request->query->get('criteria');
            $all = $request->query->get('all');

            if (!empty($criteria) && empty($all)) {
                $userFinding = $this->userRepository->getOneUserByCriteria($criteria);

                if (empty($userFinding)) {
                    return $this->json(
                        ['success' => false, 'message' => 'Utilisateur non trouvé'],
                        Response::HTTP_NOT_FOUND
                    );
                }

                return $this->json(
                    ['success' => true, 'data' => $this->normalizeUserObject($userFinding)],
                    Response::HTTP_OK
                );
            }

            if (!empty($all) && empty($criteria)) {
                $userFinding = $this->userRepository->findAll();
                return $this->json(
                    ['success' => true, 'data' => $this->normalizeUsersObjects($userFinding)],
                    Response::HTTP_OK
                );
            }

            return $this->json(
                ['success' => false, 'message' => 'Aucun critère valide fourni'],
                Response::HTTP_BAD_REQUEST
            );

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la récupération des utilisateurs", ['error' => $e->getMessage()]);
            return $this->json(
                ['success' => false, 'message' => 'Erreur serveur interne'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route(methods: ['PUT'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function put(Request $request, #[CurrentUser]User $user): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!in_array('ROLE_ADMIN', $user->getRoles()))
            {
                if ($user->getUuid() !== $data['uuid']) {
                    throw new \Exception("Utilisateur incorrect");
                }
            }

            $userPutDTO = USERPUTDTO::fromArray($data);

           $violations = $this->validator->validate($userPutDTO);


           if (count($violations) > 0) {
               $error = [];
               foreach ($violations as $violation) {
                   $error[] = $violation->getMessage();
               }
               return $this->json($error, Response::HTTP_BAD_REQUEST);
           }

           $customer = $this->userRepository->findOneBy(['uuid'=>$userPutDTO->uuid]);

            if (empty($customer)) {
                return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
            }

            $allowedFields = ['email', 'firstname', 'lastname', 'phone', 'address', 'city', 'postCode', 'country'];
            unset($data['uuid']);

            foreach ($data as $field => $value) {
                if (!in_array($field, $allowedFields)) {
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
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $deleteDTO = userDeleteDTO::fromArray($data);

            $violations = $this->validator->validate($deleteDTO);

            if (count($violations) > 0) {
                $error = [];
                foreach ($violations as $violation) {
                    $error[] = $violation->getMessage();
                }
                return $this->json($error, Response::HTTP_BAD_REQUEST);
            }

            if (empty($deleteDTO->uuid)) {
                return $this->json(
                    ['success' => false, 'message' => 'UUID utilisateur requis'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $customer = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

            if (empty($customer)) {
                return $this->json(
                    ['success' => false, 'message' => 'Utilisateur non trouvé'],
                    Response::HTTP_NOT_FOUND
                );
            }

            $this->entityManager->remove($customer);
            $this->entityManager->flush();

            return $this->json(
                ['success' => true, 'message' => 'Utilisateur supprimé avec succès'],
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la suppression de l'utilisateur", ['error' => $e->getMessage()]);
            return $this->json(
                ['success' => false, 'message' => 'Erreur serveur interne'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
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
