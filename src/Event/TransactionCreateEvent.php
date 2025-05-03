<?php

namespace App\Event;

use App\Entity\Transaction;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class TransactionCreateEvent extends Event {
    private Transaction $transaction;
    private User $user;
    public const NAME = 'transaction_create.event';
    public const TEMPLATE_NAME= 'transactionCreate';

    public function __construct( User $user,Transaction $transaction) {
        $this->transaction = $transaction;
        $this->user = $user;
    }

    public function getTransaction(): Transaction {
        return $this->transaction;
    }

    public function getUser(): User {
        return $this->user;
    }
}