<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use App\Entity\MaintenanceContract;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\Website;
use App\Traits\ExeptionTrait;
use Doctrine\ORM\EntityManagerInterface;

class MaintenanceContractService
{
    use ExeptionTrait;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function createService(User $user, Website $website, $data): MaintenanceContract
    {
        try {
            $startAt = \DateTimeImmutable::createFromFormat('Y-m-d', $data['startAt']) ?: new \DateTimeImmutable($data['startAt']);
            $firstPaymentAt = \DateTimeImmutable::createFromFormat('Y-m-d', $data['firstPaymentAt']) ?: new \DateTimeImmutable($data['firstPaymentAt']);
            $lastPaymentAt = clone $firstPaymentAt;
            $nextPaymentAt = $firstPaymentAt->modify('+30 days');
            $endAt = \DateTimeImmutable::createFromFormat('Y-m-d', $data['endAt']) ?: new \DateTimeImmutable($data['endAt']);

            $maintenanceContract = new MaintenanceContract();
            $maintenanceContract->setUser($user);
            $maintenanceContract->setWebsite($website);
            $maintenanceContract->setStartAt($startAt);
            $maintenanceContract->setEndAt($endAt);
            $maintenanceContract->setFirstPaymentAt($firstPaymentAt);
            $maintenanceContract->setNextPaymentAt($nextPaymentAt);
            $maintenanceContract->setLastPaymentAt($lastPaymentAt);
            $maintenanceContract->setMonthlyCost($data['monthlyCost']);
            $maintenanceContract->setReccurence($data['reccurence']);

            $this->entityManager->persist($maintenanceContract);
            $this->entityManager->flush();

            $notification = new Notification();
            $notification->setUser($user);
            $notification->setIsPriotity(false);
            $notification->setTitle(NotificationService::WEBSITE_MAINTENANCE_CONTRACT_CREATED_TITLE);
            $notification->setDescription(NotificationService::WEBSITE_MAINTENANCE_CONTRACT_CREATED_DESCRIPTION);

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            return $maintenanceContract;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    public function normalizeMaintenanceContract(MaintenanceContract $maintenanceContract): array
    {
        return [
            'uuid' => $maintenanceContract->getUuid(),
            'startAt' => $maintenanceContract->getStartAt()->format('d-m-Y'),
            'endAt' => $maintenanceContract->getEndAt()->format('d-m-Y'),
            'firstPaymentAt' => $maintenanceContract->getFirstPaymentAt()->format('d-m-Y'),
            'nextPaymentAt' => $maintenanceContract->getNextPaymentAt()->format('d-m-Y'),
            'lastPaymentAt' => $maintenanceContract->getLastPaymentAt()->format('d-m-Y'),
            'monthlyCost' => $maintenanceContract->getMonthlyCost(),
            'reccurence' => $maintenanceContract->getReccurence(),
            'createdAt' => $maintenanceContract->getCreatedAt()->format('d-m-Y'),
        ];
    }

    public function normalizeMaintenancesContracts(array $maintenanceContracts): array
    {
        $contracts = [];
        foreach ($maintenanceContracts as $maintenanceContract) {
            $contracts[] = $this->normalizeMaintenanceContract($maintenanceContract);
        }

        return $contracts;
    }
}
