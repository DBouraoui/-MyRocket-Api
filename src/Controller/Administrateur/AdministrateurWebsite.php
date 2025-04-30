<?php

namespace App\Controller\Administrateur;

use App\Repository\UserRepository;
use App\Repository\WebsiteRepository;
use App\service\WebsiteService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;


#[Route(path: '/api/administrateur/website', name: 'api_administrateur_website_')]
#[IsGranted("ROLE_ADMIN")]
class AdministrateurWebsite extends AbstractController
{
    public const POST_REQUIRED_FIELDS =['title', 'url', 'description', 'status', 'type', 'uuidUser'];
    public const GET_ALL_WEBSITES = 'getAllWebsites';
    public const GET_ONE_WEBSITE = 'getOneWebsite';


    public function __construct
    (
        private WebsiteService $websiteService,
        private UserRepository $userRepository,
        private CacheInterface $cache, private readonly LoggerInterface $logger, private readonly WebsiteRepository $websiteRepository, private readonly EntityManagerInterface $entityManager
    ) {

    }

    #[route(name: 'get', methods:['GET'] )]
    public function getAllWebsite() {
        try {
            $websites =  $this->websiteRepository->findAll();

            return $this->json($this->websiteService->normalizeWebsites($websites), Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error('Error fetching websites: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(name: 'post', methods: ['POST'])]
    public function post(Request $request) {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                Throw new \Exception(WebsiteService::EMPTY_DATA, Response::HTTP_NOT_FOUND);
            }

            $this->checkRequiredFields(self::POST_REQUIRED_FIELDS, $data);

            $user = $this->userRepository->findOneBy(['uuid' => $data['uuidUser']]);

            if (empty($user)) {
                Throw new \Exception(WebsiteService::USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $this->websiteService->createWebsite($data, $user);

            $this->cache->delete(self::GET_ALL_WEBSITES.$user->getUuid());
            $this->cache->delete(self::GET_ONE_WEBSITE.$user->getUuid());

            return $this->json(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_CREATED);
        } catch(\Exception $e) {
            $this->logger->error('Error creating website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Error creating website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(name: 'put', methods: ['PUT'])]
    public function update(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                Throw new \Exception(WebsiteService::EMPTY_DATA, Response::HTTP_NOT_FOUND);
            }

            if (!isset($data['uuid'])) {
                Throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_NOT_FOUND);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $data['uuid']]);

            if (!$website) {
                Throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $allowedProperties = [
                'type', 'title', 'url', 'description', 'status', 'uuidUser'
            ];

            foreach ($allowedProperties as $property) {
                $setter = 'set' . ucfirst($property);
                if (isset($data[$property])) {
                    $website->$setter($data[$property]);
                }
            }

            $this->websiteService->validate($website);

            $this->entityManager->flush();

            $this->cache->delete(self::GET_ALL_WEBSITES.$website->getUser()->getUuid());
            $this->cache->delete(self::GET_ONE_WEBSITE.$website->getUser()->getUuid());

            return $this->json(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error('Error updating website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/{uuid}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse {
        try {
            if (empty($uuid)) {
                Throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_NOT_FOUND);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $uuid]);

            if (!$website) {
                Throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($website);
            $this->entityManager->flush();

            $this->cache->delete(self::GET_ALL_WEBSITES.$website->getUser()->getUuid());
            $this->cache->delete(self::GET_ONE_WEBSITE.$website->getUser()->getUuid());

            return $this->json(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error('Error deleting website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}