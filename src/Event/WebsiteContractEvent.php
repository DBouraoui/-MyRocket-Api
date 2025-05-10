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
use App\Entity\WebsiteContract;
use Symfony\Contracts\EventDispatcher\Event;

class WebsiteContractEvent extends Event
{
    private User $user;
    private WebsiteContract $websiteContract;
    public const NAME = 'website_contract.event';
    public const TEMPLATE_NAME = 'websiteContract';

    public function __construct(User $user, WebsiteContract $websiteContract)
    {
        $this->user = $user;
        $this->websiteContract = $websiteContract;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getWebsiteContract(): WebsiteContract
    {
        return $this->websiteContract;
    }
}
