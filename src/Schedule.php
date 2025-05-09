<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->add(
                RecurringMessage::cron(
                    '#daily',
                    new RunCommandMessage('app:check-invoice-user')
                ),
            )
            ->add(
                RecurringMessage::cron(
                    '#daily',
                    new RunCommandMessage('app:resend-invoice-user')
                )
            )
            ->add(
                RecurringMessage::cron(
                    '#daily',
                    new RunCommandMessage('app:delete-notification')
                )
            )
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
        ;
    }
}
