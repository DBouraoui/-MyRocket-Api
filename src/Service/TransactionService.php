<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\WebsiteContract;
use App\Traits\ExeptionTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class TransactionService
{
    use ExeptionTrait;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function createTransaction(User $user, WebsiteContract $websiteContract)
    {
        try {
            $transaction = new Transaction();
            $transaction->setUser($user);
            $transaction->setWebsiteContract($websiteContract);
            $transaction->setAmount($websiteContract->getmonthlyCost());
            $transaction->setTva($websiteContract->getTva());
            $transaction->setIsPaid(false);

            $this->entityManager->persist($transaction);
            $this->entityManager->flush();

            return $transaction;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function normalizeTransaction(Transaction $transaction)
    {
        return [
            'uuid' => $transaction->getUuid(),
            'amount' => $transaction->getAmount(),
            'tva' => $transaction->getTva(),
            'createdAt' => $transaction->getCreatedAt()->format('d-m-Y H:i:s'),
            'user' => $transaction->getUser()->getEmail(),
            'userUuid' => $transaction->getUser()->getUuid(),
            'websiteContract' => $transaction->getWebsiteContract()->getPrestation(),
            'websiteUuid' => $transaction->getWebsiteContract()->getUuid(),
            'isPaid' => $transaction->isPaid(),
            'reminderAt' => $transaction->getReminderSentAt()?->format('d-m-Y H:i:s'),
        ];
    }

    public function normaliseTransactions(array $transactions)
    {
        $transactionsArray = [];
        foreach ($transactions as $transaction) {
            $transactionsArray[] = $this->normalizeTransaction($transaction);
        }

        return $transactionsArray;
    }
}
