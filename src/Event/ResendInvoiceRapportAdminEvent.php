<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class ResendInvoiceRapportAdminEvent extends Event
{
    public const NAME = 'resend_invoice_rapport_admin';
    public const TEMPLATE_NAME = 'resendEmailInvoiceRapportAdmin';
    private array $websiteContract;
    private User $user;

    public function __construct(User $user, array $websiteContract)
    {
        $this->websiteContract = $websiteContract;
        $this->user = $user;
    }

    public function getWebsiteContract(): array
    {
        return $this->websiteContract;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
