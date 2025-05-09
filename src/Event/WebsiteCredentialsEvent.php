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
use App\Entity\Website;
use Symfony\Contracts\EventDispatcher\Event;

class WebsiteCredentialsEvent extends Event
{
    private User $user;
    private Website $website;
    private array $configuration;
    public const TEMPLATE_NAME = 'credentials';
    public const NAME = 'website_credentials.event';

    public function __construct(User $user, Website $website, array $configuration)
    {
        $this->user = $user;
        $this->website = $website;
        $this->configuration = $configuration;
    }

    public function getWebsite(): Website
    {
        return $this->website;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }
}
