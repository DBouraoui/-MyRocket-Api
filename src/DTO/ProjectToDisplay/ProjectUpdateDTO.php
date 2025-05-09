<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\DTO\ProjectToDisplay;

use App\Traits\ProjectToDisplay;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProjectUpdateDTO
{
    use ProjectToDisplay;

    #[NotBlank]
    public ?string $uuid;
}
