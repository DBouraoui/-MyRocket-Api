<?php

namespace App\Controller;

use App\DTO\user\RegisterDTO;
use App\DTO\user\UserDeleteDTO;
use App\DTO\user\UserPutDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use App\service\EmailService;
use App\service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
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
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('api/user', name: 'user',)]
final class UserController extends AbstractController
{
    public const GET_ALL_USERS = 'getAllUsers';
    public const GET_ONE_USER = 'getOneUser';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface      $entityManager,
        private readonly LoggerInterface             $logger,
        private readonly ValidatorInterface          $validator,
        private readonly UserRepository              $userRepository,
        private readonly UserService                 $userService, private readonly EmailService $emailService,
        private readonly  CacheInterface              $cache,
    ) {

    }

    #[Route( '/register', name: 'user_register', methods: ['POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function post(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                throw new \Exception(UserService::EMPTY_DATA, Response::HTTP_NOT_FOUND);
            }

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
                throw new \Exception(UserService::USER_ALREADY_EXIST, Response::HTTP_NOT_FOUND);
            }

           $user = $this->userService->createUser($registerDTO);

            $context = [
                'template'=>'register',
                'emailUser'=>$user->getEmail(),
                'passwordUser'=>$registerDTO->password,
                'loginUrl'=> 'http://login.fr'
            ];

            $this->emailService->generate($user,'Rendez vous sur MyRocket !',$context);

            $this->cache->delete(self::GET_ALL_USERS);

            return $this->json(UserService::SUCCESS_RESPONSE, Response::HTTP_CREATED);

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

                $cacheKey = self::GET_ONE_USER.$criteria;
                $userFinding = $this->cache->get($cacheKey, function() use ($criteria) {
                    return $this->userRepository->getOneUserByCriteria($criteria);
                });

                if (empty($userFinding)) {
                   Throw new \Exception(UserService::USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
                }

                return $this->json(
                    ['success' => true, 'data' => $this->userService->normalizeUserObject($userFinding)],
                    Response::HTTP_OK
                );
            }

            if (!empty($all) && empty($criteria)) {

                $userFinding = $this->cache->get(self::GET_ALL_USERS, function(ItemInterface $item) {
                        $item->expiresAfter(3600);
                return  $this->userRepository->findAll();
                });

                return $this->json(
                    $this->userService->normalizeUsersObjects($userFinding),
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
        } catch (InvalidArgumentException $e) {
            $this->logger->error("Erreur lors de la récupération des utilisateurs", ['error' => $e->getMessage()]);
            return $this->json(
                ['success' => false, 'message' => $e->getMessage()],
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

            $allowedFields = ['email', 'firstname', 'lastname', 'phone', 'address', 'city', 'postCode', 'country', 'companyName'];
            unset($data['uuid']);

            if (isset($data['email']) && $data['email'] !== $customer->getEmail()) {
                $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
                if ($existingUser && $existingUser->getUuid() !== $customer->getUuid()) {
                    return $this->json(['error' => 'Cet email est déjà utilisé par un autre compte'], Response::HTTP_CONFLICT);
                }
            }

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

            $this->cache->delete(self::GET_ALL_USERS);

            return $this->json(
                ['message' => 'User updated successfully'],
                Response::HTTP_OK
            );

        } catch(\Exception $e) {
            $this->logger->error("Error updating user", ['error' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (InvalidArgumentException $e) {
            $this->logger->error("Error updating user", ['error' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route( path: '/{uuid}' ,methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(string $uuid): JsonResponse
    {
        try {
            if (empty($uuid)) {
                return $this->json(
                    ['success' => false, 'message' => 'UUID utilisateur requis'],
                    Response::HTTP_BAD_REQUEST
                );
            }
            $deleteDTO = new UserDeleteDTO();
            $deleteDTO->uuid = $uuid;

            $violations = $this->validator->validate($deleteDTO);

            if (count($violations) > 0) {
                $error = [];
                foreach ($violations as $violation) {
                    $error[] = $violation->getMessage();
                }
                return $this->json($error, Response::HTTP_BAD_REQUEST);
            }

            $customer = $this->userRepository->findOneBy(['uuid' => $uuid]);

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

    #[Route( path: '/me' ,methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(#[CurrentUser]User $user): JsonResponse {
        try {
            return $this->json($this->userService->normalizeUserObject($user), Response::HTTP_OK);
        }catch (\Exception $e) {
            $this->logger->error("Erreur serveur interne", ['error' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route( methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function changePassword(Request $request, #[CurrentUser]User $user): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            if (!in_array('ROLE_ADMIN', $user->getRoles()))
            {
                if ($user->getUuid() !== $data['uuid']) {
                    throw new \Exception("Utilisateur incorrect");
                }
            }

            $userPatch = $this->userRepository->findOneBy(['uuid'=>$user->getUuid()]);

            if (!$this->passwordHasher->isPasswordValid($userPatch, $data['oldPassword'])) {
                return $this->json(['error' => 'Mot de passe incorrect'], Response::HTTP_NOT_FOUND);
            }

            $userPatch->setPassword($this->passwordHasher->hashPassword($userPatch, $data['newPassword']));

            $this->entityManager->flush();

            $this->cache->delete(self::GET_ALL_USERS);

            return $this->json(['success' => true], Response::HTTP_OK);
        }  catch (\Exception $e) {
            $this->logger->error("Erreur serveur interne", ['error' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
