<?php

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class TransactionRapportAdmin extends Event
{
    public const NAME = 'transaction_rapport_admin.event';
    public const TEMPLATE_NAME= "transactionRapportAdmin";
    private array $data;
    private User $user;

    public function __construct(array $data, User $user) {
        $this->data = $data;
        $this->user = $user;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getUser(): User
    {
        return $this->user;
    }

}