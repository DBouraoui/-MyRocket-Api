<?php

namespace App\Event;

use App\Entity\User;
use App\Entity\WebsiteContract;
use Symfony\Contracts\EventDispatcher\Event;

class WebsiteContractEvent extends Event {
    private User $user;
    private WebsiteContract $websiteContract;
    public const NAME = 'website_contract.event';
    public const TEMPLATE_NAME = 'websiteContract';

    public function __construct(User $user, WebsiteContract $websiteContract) {
        $this->user = $user;
        $this->websiteContract = $websiteContract;
    }

    public function getUser(): User {
        return $this->user;
    }

    public function getWebsiteContract(): WebsiteContract {
        return $this->websiteContract;
    }
}