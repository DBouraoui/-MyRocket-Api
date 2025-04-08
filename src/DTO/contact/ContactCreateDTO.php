<?php

namespace App\DTO\contact;

use App\traits\Contact;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContactCreateDTO
{
    use Contact;
    #[NotBlank]
    public ?string $title;
    #[NotBlank]
    public ?string $description;
    #[NotBlank]
    public ?string $email;
    #[NotBlank]
    public array $tags;
}