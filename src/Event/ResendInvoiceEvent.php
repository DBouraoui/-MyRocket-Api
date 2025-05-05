<?php
namespace App\Event;

use App\Entity\User;
use App\Entity\WebsiteContract;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Relance les clients sur les factures impayés depuis plusieurs jours avant la date d'échéance
 */
class ResendInvoiceEvent extends  Event {
    public const NAME = 'invoice_resend.event';
    public const TEMPLATE_NAME = 'resendEmailInvoice';
    private User $user;
    private WebsiteContract $websiteContract;

    public function __construct(User $user, WebsiteContract $contract) {
        $this->user = $user;
        $this->websiteContract = $contract;
    }

    public function getUser(): User {
        return $this->user;
    }

    public function getWebsiteContract(): WebsiteContract {
        return $this->websiteContract;
    }
}