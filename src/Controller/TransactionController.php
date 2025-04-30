<?php

namespace App\Controller;

use App\Entity\User;
use App\service\TransactionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('api/user/transaction', name: 'app_transaction')]
#[IsGranted('IS_AUTHENTICATED')]
final class TransactionController extends AbstractController
{

    public function __construct
    (
        private readonly LoggerInterface $logger,
        private readonly TransactionService $transactionService
    )
    {
    }
        #[route(path:'/me', name: '_getme', methods: ['GET'])]
        public function getByUuid(#[CurrentUser]User $user): JsonResponse
        {
            try {
                $transactions = $user->getTransactions()->toArray();

                return new JsonResponse($this->transactionService->normaliseTransactions($transactions), Response::HTTP_OK);
            } catch(\Exception $e) {
                $this->logger->error($e->getMessage());
                return new JsonResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
}
