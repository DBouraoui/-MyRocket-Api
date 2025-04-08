<?php

namespace App\DTO\ProjectToDisplay;

use App\traits\ProjectToDisplay;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProjectCreateDTO
{
    use ProjectToDisplay;

    #[NotBlank]
    public ?string $description;
    #[NotBlank]
    public ?string $title;
    #[NotBlank]
    public array $link;
    #[NotBlank]
    public array $tags;
}