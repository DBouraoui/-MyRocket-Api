<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Event;

use App\Entity\Transaction;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Relance les clients sur les factures impayés depuis plusieurs jours avant la date d'échéance.
 */
class ResendInvoiceEvent extends Event
{
    public const NAME = 'invoice_resend.event';
    public const TEMPLATE_NAME = 'resendEmailInvoice';
    private User $user;
    private Transaction $transaction;

    public function __construct(User $user, Transaction $contract)
    {
        $this->user = $user;
        $this->transaction = $contract;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTransactions(): Transaction
    {
        return $this->transaction;
    }
}
