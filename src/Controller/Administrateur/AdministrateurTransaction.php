<?php

namespace App\Controller\Administrateur;

use App\Event\TransactionCreateEvent;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Repository\WebsiteContractRepository;
use App\service\TransactionService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route(path:'/api/administrateur/transaction', name: 'api_administrateur_transaction')]
#[IsGranted('ROLE_ADMIN')]
class AdministrateurTransaction extends AbstractController
{
    public const GET_USER_TRANSACTIONS = 'getUserTransactions';
    public const GET_ALL_USER_TRANSACTIONS = 'getAllUserTransactions';

    public function __construct
    (
        private readonly UserRepository $userRepository,
        private readonly WebsiteContractRepository $websiteContractRepository,
        private readonly TransactionService $transactionService,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly TransactionRepository $transactionRepository,
        private readonly EventDispatcherInterface $dispatcher
    )
    {
    }

    #[Route(name: '_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                throw new \Exception(TransactionService::EMPTY_DATA);
            }

            $user = $this->userRepository->findOneBy(['uuid' => $data['uuidUser']]);

            if (empty($user)) {
                throw new \Exception(TransactionService::USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $websiteContract = $this->websiteContractRepository->findOneBy(['uuid' => $data['uuidWebsiteContract']]);

            if (empty($websiteContract)) {
                throw new \Exception(TransactionService::WEBSITE_CONTRACT_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }


           $transaction =  $this->transactionService->createTransaction($user, $websiteContract);
            $this->cache->delete(self::GET_ALL_USER_TRANSACTIONS);
            $this->cache->delete(self::GET_USER_TRANSACTIONS . $user->getUuid());

            $event = new TransactionCreateEvent($user, $transaction);
            $this->dispatcher->dispatch($event, TransactionCreateEvent::NAME);

            return new JsonResponse(TransactionService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/user', name: '_getUserAndtransaction', methods: ['GET'])]
    public function getUserAndContract() {
        try {
            $users = $this->userRepository->findAll();

            $informations = [];
            foreach ($users as $user) {
                $array = [
                    'uuid' => $user->getUuid(),
                    'email' => $user->getEmail(),
                ];

                // Utiliser le nom exact de la méthode getter correspondant à votre propriété
                if (!$user->getWebsiteContract()->isEmpty()) {
                    $websiteContracts = [];
                    foreach ($user->getWebsiteContract() as $websiteContract) {
                        $websiteContracts[] = [
                            'uuid' => $websiteContract->getUuid(),
                            'name' => $websiteContract->getPrestation(),
                        ];
                    }
                    $array['websiteContracts'] = $websiteContracts;
                }

                $informations[] = $array;
            }

            return new JsonResponse($informations, Response::HTTP_OK);

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
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

                $normalizeTransaction = $this->cache->get(self::GET_USER_TRANSACTIONS.$user->getUuid(), function(ItemInterface $item) use($user) {
                    $item->expiresAfter(7200);
                    $transactions = $user->getTransactions()->toArray();
                    return $this->transactionService->normaliseTransactions($transactions);
                });

                return new JsonResponse($normalizeTransaction, Response::HTTP_OK);
            }

            if (!empty($fromAllUser)) {

                $transactionsNormalized = $this->cache->get(self::GET_ALL_USER_TRANSACTIONS, function (ItemInterface $item)  {
                    $item->expiresAfter(7200);
                    $transactions =  $this->transactionRepository->findAll();
                    return $this->transactionService->normaliseTransactions($transactions);
                });

                return new JsonResponse($transactionsNormalized, Response::HTTP_OK);
            }

            return new JsonResponse(TransactionService::ERROR_RESPONSE, Response::HTTP_OK);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
            return new JsonResponse($exception->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}