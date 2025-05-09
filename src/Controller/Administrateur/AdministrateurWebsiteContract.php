<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Administrateur;

use App\Event\WebsiteContractEvent;
use App\Repository\WebsiteRepository;
use App\Service\WebsiteService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/api/administrateur/website-contract', name: 'api_administrateur_website_contract')]
#[IsGranted('ROLE_ADMIN')]
class AdministrateurWebsiteContract extends AbstractController
{
    public function __construct(
        private WebsiteService $websiteService,
        private readonly WebsiteRepository $websiteRepository,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Créer un contrat pour un site web.
     */
    #[Route(name: '_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                throw new \Exception(WebsiteService::EMPTY_DATA);
            }

            if (empty($data['uuidWebsite'])) {
                throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_BAD_REQUEST);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $data['uuidWebsite']]);

            if (empty($website)) {
                throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_BAD_REQUEST);
            }

            $user = $website->getUser();

            if (empty($user)) {
                throw new \Exception(WebsiteService::USER_NOT_FOUND, Response::HTTP_BAD_REQUEST);
            }

            $contract = $website->getWebsiteContract();

            if (!empty($contract)) {
                throw new \Exception(WebsiteService::WEBSITE_CONTRACT_ALREADY_EXIST, Response::HTTP_BAD_REQUEST);
            }

            $websiteContract = $this->websiteService->createWebsiteContract($data, $user, $website);

            $event = new WebsiteContractEvent($user, $websiteContract);
            $this->dispatcher->dispatch($event, WebsiteContractEvent::NAME);

            return new JsonResponse(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return new JsonResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime un contrat d'un site web.
     *
     * @return JsonResponse
     */
    #[Route(path: '/{uuid}', name: '_delte', methods: ['DELETE'])]
    public function delete($uuid)
    {
        try {
            if (empty($uuid)) {
                throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_BAD_REQUEST);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $uuid]);

            if (empty($website)) {
                throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_BAD_REQUEST);
            }

            $websiteContract = $website->getWebsiteContract();

            if (empty($websiteContract)) {
                throw new \Exception(WebsiteService::WEBSITE_CONTRACT_NOT_FOUND, Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->remove($websiteContract);
            $this->entityManager->flush();

            return new JsonResponse(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return new JsonResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
