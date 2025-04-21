<?php

namespace App\Controller;

use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Repository\WebsiteContractRepository;
use App\service\TransactionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('api/transaction', name: 'app_transaction')]
final class TransactionController extends AbstractController
{
    public const POST_REQUIRED_FIELDS = ['uuidUser','uuidWebsiteContract'];
    public function __construct(private readonly LoggerInterface $logger, private readonly UserRepository $userRepository, private readonly WebsiteContractRepository $websiteContractRepository, private readonly TransactionService $transactionService, private readonly TransactionRepository $transactionRepository)
    {
    }

    #[Route( name: '_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                Throw new \Exception(TransactionService::EMPTY_DATA);
            }

            $user = $this->userRepository->findOneBy(['uuid' => $data['uuidUser']]);

            if (empty($user)) {
                Throw new \Exception(TransactionService::USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $websiteContract = $this->websiteContractRepository->findOneBy(['uuid' => $data['uuidWebsiteContract']]);

            if (empty($websiteContract)) {
                Throw new \Exception(TransactionService::WEBSITE_CONTRACT_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $this->transactionService->createTransaction($user, $websiteContract);

            return new JsonResponse(TransactionService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse($e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    #[route(name: '_get', methods: ['GET'])]
    public function get(Request $request): JsonResponse {
        try {
            $fromUser = $request->query->get('fromUser');
            $fromAllUser = $request->query->get('fromAllUser');

            if (empty($fromUser) && empty($fromAllUser)) {
                Throw new \Exception(TransactionService::EMPTY_DATA, Response::HTTP_NOT_FOUND);
            }

            if (!empty($fromUser)) {
                $user = $this->userRepository->findOneBy(['uuid' => $fromUser]);

                if (empty($user)) {
                    Throw new \Exception(TransactionService::USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
                }

               $transactions = $user->getTransactions()->toArray();

                return new JsonResponse($this->transactionService->normaliseTransactions($transactions), Response::HTTP_OK);
            }

            if (!empty($fromAllUser)) {
                $transactions = $this->transactionRepository->findAll();

                return new JsonResponse($this->transactionService->normaliseTransactions($transactions), Response::HTTP_OK);
            }

            return new JsonResponse(TransactionService::ERROR_RESPONSE, Response::HTTP_OK);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
            return new JsonResponse($exception->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
