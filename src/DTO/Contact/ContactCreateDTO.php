<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\DTO\Contact;

use App\Traits\Contact;
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
