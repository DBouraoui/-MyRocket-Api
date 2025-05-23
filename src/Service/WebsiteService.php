<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Entity\Website;
use App\Entity\WebsiteContract;
use App\Entity\WebsiteMutualised;
use App\Entity\WebsiteVps;
use App\Traits\ExeptionTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WebsiteService
{
    use ExeptionTrait;
    public const CONFIGURATION_NOT_FOUND = 'Configuration not found';
    public const CONFIGURATION_ALREADY_EXISTS = 'Configuration already exists';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function createWebsite(array $data, User $user): Website
    {
        try {
            $website = new Website();
            $website->setTitle($data['title']);
            $website->setUrl($data['url']);
            $website->setDescription($data['description']);
            $website->setStatus($data['status']);
            $website->setType($data['type']);
            $website->setUser($user);

            self::validate($website);

            $this->entityManager->persist($website);
            $this->entityManager->flush();

            $notification = new Notification();
            $notification->setUser($user);
            $notification->setIsPriotity(false);
            $notification->setTitle(NotificationService::WEBSITE_CREATED_TITLE);
            $notification->setDescription(NotificationService::WEBSITE_CREATED_DESCRIPTION);

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            return $website;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    public function createMutualisedConfiguration(array $data, Website $website): WebsiteMutualised
    {
        try {
            $websiteMutualised = new WebsiteMutualised();
            $websiteMutualised->setUsername($data['username']);
            $websiteMutualised->setPassword($data['password']);
            $websiteMutualised->setAddress($data['address']);
            $websiteMutualised->setPort($data['port']);
            $websiteMutualised->setWebsite($website);

            $this->entityManager->persist($websiteMutualised);
            $this->entityManager->flush();

            $notification = new Notification();
            $notification->setUser($website->getUser());
            $notification->setIsPriotity(false);
            $notification->setTitle(NotificationService::WEBSITE_CONFIG_CREATED_TITLE);
            $notification->setDescription(NotificationService::WEBSITE_CONFIG_CREATED_DESCRIPTION);

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            return $websiteMutualised;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    public function createVPSConfiguration(array $data, Website $website): WebsiteVps
    {
        try {
            $websitevps = new WebsiteVps();
            $websitevps->setUsername($data['username']);
            $websitevps->setPassword($data['password']);
            $websitevps->setAddress($data['address']);
            $websitevps->setPort($data['port']);
            $websitevps->setPublicKey($data['publicKey']);
            $websitevps->setWebsite($website);

            $this->entityManager->persist($websitevps);
            $this->entityManager->flush();

            $notification = new Notification();
            $notification->setUser($website->getUser());
            $notification->setIsPriotity(false);
            $notification->setTitle(NotificationService::WEBSITE_CONFIG_CREATED_TITLE);
            $notification->setDescription(NotificationService::WEBSITE_CONFIG_CREATED_DESCRIPTION);

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            return $websitevps;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    public function createWebsiteContract(array $data, User $user, Website $website): WebsiteContract
    {
        try {
            $websiteContract = new WebsiteContract();

            $firstPaymentAt = \DateTimeImmutable::createFromFormat('Y-m-d', $data['firstPaymentAt']) ?: new \DateTimeImmutable($data['firstPaymentAt']);
            $lastPaymentAt = \DateTimeImmutable::createFromFormat('Y-m-d', $data['lastPaymentAt']) ?: new \DateTimeImmutable($data['lastPaymentAt']);
            $nextPaymentAt = \DateTimeImmutable::createFromFormat('Y-m-d', $data['nextPaymentAt']) ?: new \DateTimeImmutable($data['nextPaymentAt']);

            $websiteContract->setWebsite($website);
            $websiteContract->setUser($user);
            $websiteContract->setFirstPaymentAt($firstPaymentAt);
            $websiteContract->setLastPaymentAt($lastPaymentAt);
            $websiteContract->setNextPaymentAt($nextPaymentAt);
            $websiteContract->setPrestation($data['prestation']);
            $websiteContract->setmonthlyCost($data['monthlyCost']);
            $websiteContract->setTva($data['tva']);
            $websiteContract->setReccurence($data['reccurence']);

            $this->entityManager->persist($websiteContract);
            $this->entityManager->flush();

            $notification = new Notification();
            $notification->setUser($user);
            $notification->setTitle(NotificationService::WEBSITE_CONTRACT_CREATED_TITLE);
            $notification->setDescription(NotificationService::WEBSITE_CONTRACT_CREATED_DESCRIPTION);
            $notification->setIsPriotity(false);

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            return $websiteContract;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function validate(Website $website): void
    {
        $errors = $this->validator->validate($website);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            $errorMessage = implode(', ', $errorMessages);

            throw new \Exception(sprintf(self::ERROR_FILEDS_DATA, $errorMessage), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Normalise une entité Website en tableau.
     */
    public function normalizeWebsite(Website $website): array
    {
        return [
            'uuid' => $website->getUuid(),
            'title' => $website->getTitle(),
            'url' => $website->getUrl(),
            'description' => $website->getDescription(),
            'status' => $website->getStatus(),
            'type' => $website->getType(),
            'createdAt' => $website->getCreatedAt()?->format('m-d-Y'),
            'updatedAt' => $website->getUpdatedAt()?->format('m-d-Y'),
        ];
    }

    /**
     * Normalise un tableau d'entités Website en tableau de tableaux.
     */
    public function normalizeWebsites(array $websites): array
    {
        $websitesArray = [];
        foreach ($websites as $website) {
            $websitesArray[] = $this->normalizeWebsite($website);
        }

        return $websitesArray;
    }

    public function normalizeWebsiteContract(WebsiteContract $websiteContract): array
    {
        return [
            'uuid' => $websiteContract->getUuid(),
            'monthlyCost' => $websiteContract->getmonthlyCost(),
            'tva' => $websiteContract->getTva(),
            'reccurence' => $websiteContract->getReccurence(),
            'createdAt' => $websiteContract->getCreatedAt()?->format('m-d-Y'),
            'updatedAt' => $websiteContract->getUpdatedAt()?->format('m-d-Y'),
            'prestation' => $websiteContract->getPrestation(),
            'firstPaymentAt' => $websiteContract->getFirstPaymentAt()?->format('m-d-Y'),
            'lastPaymentAt' => $websiteContract->getLastPaymentAt()?->format('m-d-Y'),
            'nextPaymentAt' => $websiteContract->getNextPaymentAt()?->format('m-d-Y'),
        ];
    }

    public function normalizeWebsitesContracts(array $websitesContracts): array
    {
        $websitesArray = [];
        foreach ($websitesContracts as $websiteContract) {
            $websitesArray[] = $this->normalizeWebsiteContract($websiteContract);
        }

        return $websitesArray;
    }
}
