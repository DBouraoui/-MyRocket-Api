<?php

namespace App\service;

use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\WebsiteContract;
use App\traits\ExeptionTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class TransactionService
{
    use ExeptionTrait;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function createTransaction(User $user, WebsiteContract $websiteContract) {
        try {
            $transaction = new Transaction();
            $transaction->setUser($user);
            $transaction->setWebsiteContract($websiteContract);
            $transaction->setAmount(($websiteContract->getAnnualCost() / 12));
            $transaction->setTva($websiteContract->getTva());

            $this->entityManager->persist($transaction);
            $this->entityManager->flush();

            return $transaction;
        } catch(\Exception $e) {
            Throw new \Exception($e->getMessage(),Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function normalizeTransaction(Transaction $transaction) {
        return [
            'uuid' => $transaction->getUuid(),
            'amount' => $transaction->getAmount(),
            'tva' => $transaction->getTva(),
            'createdAt' => $transaction->getCreatedAt(),
            'user' => $transaction->getUser()->getEmail(),
            'userUuid' => $transaction->getUser()->getUuid(),
            'websiteContract'=> $transaction->getWebsiteContract()->getPrestation(),
            'websiteUuid' => $transaction->getWebsiteContract()->getUuid(),
        ];
    }

    public function normaliseTransactions(array $transactions) {
        $transactionsArray= [];
        foreach ($transactions as $transaction) {
            $transactionsArray[] = $this->normalizeTransaction($transaction);
        }
        return $transactionsArray;
    }
}