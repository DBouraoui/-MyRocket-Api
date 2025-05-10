<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Administrateur;

use App\Repository\WebsiteRepository;
use App\Service\WebsiteService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/administrateur/website-config', name: 'app_administrateur_website_config_')]
#[IsGranted('ROLE_ADMIN')]
final class AdministrateurWebsiteConfig extends AbstractController
{
    public const PUT_ALLOW_FIELDS = ['username', 'password', 'address', 'port'];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly WebsiteRepository $websiteRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly WebsiteService $websiteService,
    ) {
    }

    /**
     * Créer une configuration mutualisée.
     */
    #[Route(path: '/mutualised', name: 'post_mutualised', methods: ['POST'])]
    public function postMutualised(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                throw new \Exception(WebsiteService::EMPTY_DATA, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $data['uuidWebsite']]);

            if (empty($website)) {
                throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (!empty($website->getWebsiteVps()) || !empty($website->getWebsiteMutualised())) {
                throw new \Exception(WebsiteService::CONFIGURATION_ALREADY_EXISTS, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (empty($website)) {
                throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $this->websiteService->createMutualisedConfiguration($data, $website);

            return $this->json(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return new JsonResponse(['error' => $e->getMessage()], $e->getCode());
        }
    }

    /**
     * Met à jour une configuration de site mutualisé existante.
     */
    #[Route(path: '/mutualised', name: 'put_mutualised', methods: ['PUT'])]
    public function putMutualised(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                throw new \Exception(WebsiteService::EMPTY_DATA, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (empty($data['uuidWebsite'])) {
                throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $data['uuidWebsite']]);

            if (empty($website)) {
                throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $websiteMutualised = $website->getWebsiteMutualised();

            if (empty($websiteMutualised)) {
                throw new \Exception(WebsiteService::CONFIGURATION_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            foreach (self::PUT_ALLOW_FIELDS as $field) {
                if (isset($data[$field])) {
                    $setter = 'set' . ucfirst($field);
                    if (method_exists($websiteMutualised, $setter)) {
                        $websiteMutualised->{$setter}($data[$field]);
                    }
                }
            }

            $this->entityManager->flush();

            return $this->json(
                WebsiteService::SUCCESS_RESPONSE,
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    /**
     * Supprime une configuration mutualisé via sont uuid.
     */
    #[Route(path: '/mutualised/{uuid}', name: 'delete_mutualised_uuid', methods: ['DELETE'])]
    public function deleteMutualised($uuid)
    {
        try {
            if (empty($uuid)) {
                throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $uuid]);

            if (empty($website)) {
                throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $websiteMutualised = $website->getWebsiteMutualised();

            if (empty($websiteMutualised)) {
                throw new \Exception(WebsiteService::CONFIGURATION_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($websiteMutualised);
            $this->entityManager->flush();

            return $this->json(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return new JsonResponse(['error' => $e->getMessage()], $e->getCode());
        }
    }

    // ------------------------------------------_VPS--------------------------------

    #[Route(path: '/vps', name: 'post_vps', methods: ['POST'])]
    public function postVPS(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                throw new \Exception(WebsiteService::EMPTY_DATA, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $data['uuidWebsite']]);

            if (!empty($website->getWebsiteVps()) || !empty($website->getWebsiteMutualised())) {
                throw new \Exception(WebsiteService::CONFIGURATION_ALREADY_EXISTS, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (empty($website)) {
                throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $this->websiteService->createVPSConfiguration($data, $website);

            return $this->json(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/vps', name: 'put_vps', methods: ['PUT'])]
    public function putVPS(Request $request)
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                throw new \Exception(WebsiteService::EMPTY_DATA, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (empty($data['uuidWebsite'])) {
                throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $data['uuidWebsite']]);

            if (empty($website)) {
                throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $websiteVPS = $website->getWebsiteVps();
            if (empty($websiteVPS)) {
                throw new \Exception(WebsiteService::CONFIGURATION_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $allowedFields = ['username', 'password', 'address', 'port', 'publicKey'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $setter = 'set' . ucfirst($field);
                    if (method_exists($websiteVPS, $setter)) {
                        $websiteVPS->{$setter}($data[$field]);
                    }
                }
            }

            $this->entityManager->flush();

            return $this->json(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la mise à jour: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    #[Route(path: '/vps/{uuid}', name: 'delete_vps_uuid', methods: ['DELETE'])]
    public function deleteVPS($uuid): JsonResponse
    {
        try {
            if (empty($uuid)) {
                throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $uuid]);

            if (empty($website)) {
                throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $websiteVPS = $website->getWebsiteVps();

            if (empty($websiteVPS)) {
                throw new \Exception(WebsiteService::CONFIGURATION_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($websiteVPS);
            $this->entityManager->flush();

            return new JsonResponse(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
