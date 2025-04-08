<?php

namespace App\DTO\user;

use App\traits\User;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserDeleteDTO
{
    #[NotBlank]
    public ?string $uuid;

    use User;
}