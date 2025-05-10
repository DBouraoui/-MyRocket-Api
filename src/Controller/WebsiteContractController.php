<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\User;
use App\Service\WebsiteService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/user/website/contract', name: 'app_website_contract')]
#[IsGranted('IS_AUTHENTICATED')]
final class WebsiteContractController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly WebsiteService $websiteService,
    ) {
    }

    #[Route(path: '/me', name: '_get_my_contract', methods: ['GET'])]
    public function getMyContract(#[CurrentUser] User $user)
    {
        try {
            $websitesContract = $user->getWebsiteContract()->toArray();

            return new JsonResponse($this->websiteService->normalizeWebsitesContracts($websitesContract), Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return new JsonResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/get/all', name: '_getall', methods: ['GET'])]
    public function getAllInformation(#[CurrentUser] User $user)
    {
        try {
            $website = $user->getWebsites();
            $array = [];
            foreach ($website as $websiteContract) {
                $websiteData = [
                    'uuid' => $websiteContract->getUuid(),
                    'title' => $websiteContract->getTitle(),
                    'url' => $websiteContract->getUrl(),
                    'description' => $websiteContract->getDescription(),
                    'status' => $websiteContract->getStatus(),
                    'type' => $websiteContract->getType(),
                    'createdAt' => $websiteContract->getCreatedAt()->format('d-m-Y'),
                    'updatedAt' => $websiteContract->getUpdatedAt()->format('d-m-Y'),
                    'hasConfig' => $websiteContract->getWebsiteVps() || $websiteContract->getWebsiteMutualised(),
                ];

                // Ajouter le contrat du site web s'il existe
                if ($websiteContract->getWebsiteContract()) {
                    $websiteData['contract'] = [
                        'uuid' => $websiteContract->getWebsiteContract()->getUuid(),
                        'monthlyCost' => $websiteContract->getWebsiteContract()->getmonthlyCost(),
                        'tva' => $websiteContract->getWebsiteContract()->getTva(),
                        'reccurence' => $websiteContract->getWebsiteContract()->getReccurence(),
                        'createdAt' => $websiteContract->getWebsiteContract()->getCreatedAt()->format('d-m-Y'),
                        'updatedAt' => $websiteContract->getWebsiteContract()->getUpdatedAt()->format('d-m-Y'),
                        'prestation' => $websiteContract->getWebsiteContract()->getPrestation(),
                        'firstPaymentAt' => $websiteContract->getWebsiteContract()->getFirstPaymentAt()->format('d-m-Y'),
                        'lastPaymentAt' => $websiteContract->getWebsiteContract()->getLastPaymentAt()->format('d-m-Y'),
                        'nextPaymentAt' => $websiteContract->getWebsiteContract()->getNextPaymentAt()->format('d-m-Y'),
                    ];
                }

                // Ajouter le contrat de maintenance s'il existe
                if ($websiteContract->getMaintenanceContract()) {
                    $websiteData['maintenanceContract'] = [
                        'uuid' => $websiteContract->getMaintenanceContract()->getUuid(),
                        'startAt' => $websiteContract->getMaintenanceContract()->getStartAt()->format('d-m-Y'),
                        'monthlyCost' => $websiteContract->getMaintenanceContract()->getMonthlyCost(),
                        'endAt' => $websiteContract->getMaintenanceContract()->getEndAt()->format('d-m-Y'),
                        'reccurence' => $websiteContract->getMaintenanceContract()->getReccurence(),
                        'createdAt' => $websiteContract->getMaintenanceContract()->getCreatedAt()->format('d-m-Y'),
                        'firstPaymentAt' => $websiteContract->getMaintenanceContract()->getFirstPaymentAt()->format('d-m-Y'),
                        'lastPaymentAt' => $websiteContract->getMaintenanceContract()->getLastPaymentAt()->format('d-m-Y'),
                        'nextPaymentAt' => $websiteContract->getMaintenanceContract()->getNextPaymentAt()->format('d-m-Y'),
                    ];
                }

                $array[] = $websiteData;
            }

            return new JsonResponse($array, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return new JsonResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
