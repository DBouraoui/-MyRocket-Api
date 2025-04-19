<?php

namespace App\service;

use App\Entity\User;
use App\Entity\Website;
use App\Entity\WebsiteMutualised;
use App\Entity\WebsiteVps;
use App\traits\ExeptionTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WebsiteService
{
    use ExeptionTrait;

    public const WEBSITE_NOT_FOUND = "Website not found";
    public const CONFIGURATION_NOT_FOUND = "Configuration not found";
    public const CONFIGURATION_ALREADY_EXISTS = "Configuration already exists";
    public const MISSING_URL_PARAMETER = "Missing URL parameter";

    public function __construct
    (
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
    )
    {
    }


    public function createWebsite(array $data,User $user): Website
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

            return $website;
        } catch (\Exception $e) {
            Throw new \Exception($e->getMessage(),$e->getCode());
        }
    }

    public function createMutualisedConfiguration(array $data, Website $website): WebsiteMutualised {
        try {
            $websiteMutualised = new WebsiteMutualised();
            $websiteMutualised->setUsername($data['username']);
            $websiteMutualised->setPassword($data['password']);
            $websiteMutualised->setAddress($data['address']);
            $websiteMutualised->setPort($data['port']);
            $websiteMutualised->setWebsite($website);

            $this->entityManager->persist($websiteMutualised);
            $this->entityManager->flush();

            return $websiteMutualised;
        } catch(\Exception $e) {
            Throw new \Exception($e->getMessage(),$e->getCode());
        }
    }

    public function createVPSConfiguration(array $data, Website $website): WebsiteVps
    {
        try {
            $websitevps = new Websitevps();
            $websitevps->setUsername($data['username']);
            $websitevps->setPassword($data['password']);
            $websitevps->setAddress($data['address']);
            $websitevps->setPort($data['port']);
            $websitevps->setPublicKey($data['publicKey']);
            $websitevps->setWebsite($website);

            $this->entityManager->persist($websitevps);
            $this->entityManager->flush();
            return $websitevps;
        } catch(\Exception $e) {
            Throw new \Exception($e->getMessage(),$e->getCode());
        }
    }

    public function validate(Website $website): void {
        $errors = $this->validator->validate($website);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            $errorMessage = implode(", ", $errorMessages);

            Throw new \Exception(sprintf(self::ERROR_FILEDS_DATA, $errorMessage), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Normalise une entité Website en tableau
     */
    public function normalizeWebsite(Website $website): array {
        return [
            "uuid" => $website->getUuid(),
            "title" => $website->getTitle(),
            "url" => $website->getUrl(),
            "description" => $website->getDescription(),
            "status" => $website->getStatus(),
            "type" => $website->getType(),
            "createdAt" => $website->getCreatedAt()?->format('m-d-Y'),
            "updatedAt" => $website->getUpdatedAt()?->format('m-d-Y'),
        ];
    }

    /**
     * Normalise un tableau d'entités Website en tableau de tableaux
     */
    public function normalizeWebsites(array $websites): array {
        $websitesArray = [];
        foreach ($websites as $website) {
            $websitesArray[] = $this->normalizeWebsite($website);
        }
        return $websitesArray;
    }
}
