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
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/utilisateur/notification', name: 'api_utilisateur_notification')]
#[IsGranted('IS_AUTHENTICATED')]
class NotificationController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly NotificationService $notificationService,
        private readonly NotificationRepository $notificationRepository
    ) {
    }

    #[Route(name: '_notification', methods: ['GET'])]
    public function get(#[CurrentUser] User $user): JsonResponse
    {
        try {
            if (!$user instanceof User) {
                return new JsonResponse(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
            }

            $notification = $user->getNotifications();

            if (empty($notification)) {
                return new JsonResponse(['message' => 'Notification not found'], Response::HTTP_NOT_FOUND);
            }

            $notificationNormalize = $this->notificationService->normalizes($notification);

            return $this->json($notificationNormalize, Response::HTTP_OK);
        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());

            return $this->json(['error' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/{uuid}', name: '_patch', methods: ['PATCH'])]
    public function patch(#[CurrentUser] User $user, string $uuid): JsonResponse
    {
        try {
            if (empty($uuid)) {
                return new JsonResponse(['message' => 'Uuid cannot be empty'], Response::HTTP_NOT_FOUND);
            }

            $notification = $this->notificationRepository->findOneBy(['uuid' => $uuid]);

            if (empty($notification)) {
                return new JsonResponse(['message' => 'Notification not found'], Response::HTTP_NOT_FOUND);
            }

            if ($notification->getUser() !== $user) {
                return new JsonResponse(['message' => 'Notification not allowed'], Response::HTTP_FORBIDDEN);
            }

            $this->notificationService->valideNotification($notification);

            return $this->json(['success' => true], Response::HTTP_OK);
        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());

            return $this->json(['error' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/all', name: '_patch_all', methods: ['DELETE'])]
    public function patchAllNotification(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $notifications = $user->getNotifications();

            if (empty($notifications)) {
                return new JsonResponse(['message' => 'Notification not found'], Response::HTTP_NOT_FOUND);
            }

            $this->notificationService->valideAllNotifications($notifications);

            return $this->json(['success' => true], Response::HTTP_OK);
        } catch (\Throwable $th) {
            $this->logger->error($th->getMessage());

            return $this->json(['error' => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
