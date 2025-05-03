<?php

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class UserRegistredEvent extends Event {
    public const NAME = 'user_registred.event';
    private User $user;
    private string $password;

    public function __construct(User $user, string $password) {
        $this->user = $user;
        $this->password = $password;
    }

    public function getUser(): User {
        return $this->user;
    }

    public function getPassword(): string {
        return $this->password;
    }
}