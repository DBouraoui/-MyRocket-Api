<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\WebsiteRepository;
use App\service\WebsiteService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/website', name: 'app_website_')]
final class WebsiteController extends AbstractController
{
    public const POST_REQUIRED_FIELDS =['title', 'url', 'description', 'status', 'type', 'uuidUser'];

    public function __construct(
        private readonly LoggerInterface        $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly WebsiteRepository      $websiteRepository,
        private readonly UserRepository $userRepository,
        private readonly WebsiteService $websiteService,
    ) {
    }

    /**
     * Crée un nouveau site web
     */
    #[Route(name: 'post', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
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

            $website = $this->websiteService->createWebsite($data, $user);

            return $this->json(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_CREATED);
        } catch(\Exception $e) {
            $this->logger->error('Error creating website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère un ou tous les sites web
     */
    #[Route(name: 'get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function get(Request $request, #[CurrentUser]User $user): JsonResponse {
        try {
            $all = $request->query->get('all', false);
            $uuid = $request->query->get('uuid', null);

            if ($all) {
                $websites = $user->getWebsites()->toArray();
                $websiteArray = $this->websiteService->normalizeWebsites($websites);

                return $this->json($websiteArray, Response::HTTP_OK);
            } elseif ($uuid) {
                $website = $this->websiteRepository->findOneBy(['uuid' => $uuid]);

                if (!$website) {
                    Throw new Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
                }

                $websiteArray = $this->websiteService->normalizeWebsite($website);
                return $this->json($websiteArray, Response::HTTP_OK);
            } else {
               Throw new Exception(WebsiteService::MISSING_URL_PARAMETER, Response::HTTP_EXPECTATION_FAILED);
            }
        } catch(\Exception $e) {
            $this->logger->error('Error fetching websites: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Met à jour un site web existant
     */
    #[Route(name: 'put', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
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

            return $this->json(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error('Error updating website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime un site web
     */
    #[Route(path: '/{uuid}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
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

            return $this->json(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error('Error deleting website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}