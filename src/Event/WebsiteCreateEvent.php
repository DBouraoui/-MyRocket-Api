<?php

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class WebsiteCreateEvent extends Event {
    private User $user;
    public const NAME = 'website_create.event';
    public const TEMPLATE_NAME = 'websiteCreate';

    public function __construct(User $user) {
        $this->user = $user;
    }

    public function getUser(): User {
        return $this->user;
    }
}