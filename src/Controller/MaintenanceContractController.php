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
use App\Service\MaintenanceContractService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/user/maintenance/contract', name: 'app_maintenance_contract')]
#[IsGranted('IS_AUTHENTICATED')]
final class MaintenanceContractController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MaintenanceContractService $maintenanceContractService,
    ) {
    }

    #[Route(path: '/me', name: '_getme', methods: ['GET'])]
    public function getme(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $maintenanceContract = $user->getMaintenanceContracts()->toArray();

            return new JsonResponse($this->maintenanceContractService->normalizeMaintenancesContracts($maintenanceContract), Response::HTTP_OK);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());

            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
