<?php

namespace App\DTO\ProjectToDisplay;

use App\traits\ProjectToDisplay;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProjectUpdateDTO
{
    use ProjectToDisplay;

    #[NotBlank]
    public ?string $uuid;
}