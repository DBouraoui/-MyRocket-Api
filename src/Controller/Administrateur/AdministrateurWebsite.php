<?php

namespace App\Controller\Administrateur;

use App\Repository\UserRepository;
use App\Repository\WebsiteRepository;
use App\service\WebsiteService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;


#[Route(path: '/api/administrateur/website', name: 'api_administrateur_website_')]
#[IsGranted("ROLE_ADMIN")]
class AdministrateurWebsite extends AbstractController
{
    public const POST_REQUIRED_FIELDS =['title', 'url', 'description', 'status', 'type', 'uuidUser'];
    public const GET_ALL_WEBSITES = 'getAllWebsites';
    public const GET_ONE_WEBSITE = 'getOneWebsite';


    public function __construct
    (
        private WebsiteService $websiteService,
        private UserRepository $userRepository,
        private CacheInterface $cache, private readonly LoggerInterface $logger, private readonly WebsiteRepository $websiteRepository, private readonly EntityManagerInterface $entityManager
    ) {

    }

    #[route(name: 'get', methods:['GET'] )]
    public function getAllWebsite() {
        try {
            $websites =  $this->websiteRepository->findAll();

            return $this->json($this->websiteService->normalizeWebsites($websites), Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error('Error fetching websites: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(name: 'post', methods: ['POST'])]
    public function post(Request $request) {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                Throw new \Exception(WebsiteService::EMPTY_DATA, Response::HTTP_NOT_FOUND);
            }

            $this->checkRequiredFields(self::POST_REQUIRED_FIELDS, $data);

            $user = $this->userRepository->findOneBy(['uuid' => $data['uuidUser']]);

            if (empty($user)) {
                Throw new \Exception(WebsiteService::USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $this->websiteService->createWebsite($data, $user);

            $this->cache->delete(self::GET_ALL_WEBSITES.$user->getUuid());
            $this->cache->delete(self::GET_ONE_WEBSITE.$user->getUuid());

            return $this->json(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_CREATED);
        } catch(\Exception $e) {
            $this->logger->error('Error creating website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Error creating website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(name: 'put', methods: ['PUT'])]
    public function update(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                Throw new \Exception(WebsiteService::EMPTY_DATA, Response::HTTP_NOT_FOUND);
            }

            if (!isset($data['uuid'])) {
                Throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_NOT_FOUND);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $data['uuid']]);

            if (!$website) {
                Throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $allowedProperties = [
                'type', 'title', 'url', 'description', 'status', 'uuidUser'
            ];

            foreach ($allowedProperties as $property) {
                $setter = 'set' . ucfirst($property);
                if (isset($data[$property])) {
                    $website->$setter($data[$property]);
                }
            }

            $this->websiteService->validate($website);

            $this->entityManager->flush();

            $this->cache->delete(self::GET_ALL_WEBSITES.$website->getUser()->getUuid());
            $this->cache->delete(self::GET_ONE_WEBSITE.$website->getUser()->getUuid());

            return $this->json(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error('Error updating website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/{uuid}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse {
        try {
            if (empty($uuid)) {
                Throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_NOT_FOUND);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $uuid]);

            if (!$website) {
                Throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($website);
            $this->entityManager->flush();

            $this->cache->delete(self::GET_ALL_WEBSITES.$website->getUser()->getUuid());
            $this->cache->delete(self::GET_ONE_WEBSITE.$website->getUser()->getUuid());

            return $this->json(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error('Error deleting website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[route(path: '/all-informations', name: '_getallinformation', methods: ['GET'])]
    public function getUserAndWebsiteInformations() {
        try {
            $users = $this->userRepository->findAll();
            $array = [];

            foreach ($users as $user) {
                $websites = $user->getWebsites();

                foreach ($websites as $websiteContract) {
                    $websiteData = [
                        'uuid' => $websiteContract->getUuid(),
                        'title' => $websiteContract->getTitle(),
                        'url' => $websiteContract->getUrl(),
                        'description' => $websiteContract->getDescription(),
                        'status' => $websiteContract->getStatus(),
                        'type' => $websiteContract->getType(),
                        'createdAt' => $websiteContract->getCreatedAt()->format("d-m-Y"),
                        'updatedAt' => $websiteContract->getUpdatedAt()->format("d-m-Y")
                    ];

                    $websiteData['user'] = [
                        'uuid' => $websiteContract->getUser()->getUuid(),
                        'companyName'=> $websiteContract->getUser()->getCompanyName(),
                        'firstname'=> $websiteContract->getUser()->getFirstName(),
                        'lastname'=> $websiteContract->getUser()->getLastName(),
                        'email'=> $websiteContract->getUser()->getEmail(),
                        'phone'=> $websiteContract->getUser()->getPhone(),
                        'address'=> $websiteContract->getUser()->getAddress(),
                        'city'=> $websiteContract->getUser()->getCity(),
                        'postCode'=> $websiteContract->getUser()->getPostCode(),
                        'country'=> $websiteContract->getUser()->getCountry(),
                        'createdAt' =>$websiteContract->getUser()->getCreatedAt()->format("d-m-Y"),
                        'updatedAt' =>$websiteContract->getUser()->getUpdatedAt()->format("d-m-Y")
                    ];

                    // Ajouter le contrat du site web s'il existe
                    if ($websiteContract->getWebsiteContract()) {
                        $websiteData['contract'] = [
                            'uuid' => $websiteContract->getWebsiteContract()->getUuid(),
                            'annualCost' => $websiteContract->getWebsiteContract()->getAnnualCost(),
                            'tva' => $websiteContract->getWebsiteContract()->getTva(),
                            'reccurence' => $websiteContract->getWebsiteContract()->getReccurence(),
                            'createdAt' => $websiteContract->getWebsiteContract()->getCreatedAt()->format("d-m-Y"),
                            'updatedAt' => $websiteContract->getWebsiteContract()->getUpdatedAt()->format("d-m-Y"),
                            'prestation' => $websiteContract->getWebsiteContract()->getPrestation(),
                            'firstPaymentAt' => $websiteContract->getWebsiteContract()->getFirstPaymentAt()->format("d-m-Y"),
                            'lastPaymentAt' => $websiteContract->getWebsiteContract()->getLastPaymentAt()->format("d-m-Y"),
                            'nextPaymentAt' => $websiteContract->getWebsiteContract()->getNextPaymentAt()->format("d-m-Y"),
                        ];
                    }

                    // Ajouter le contrat de maintenance s'il existe
                    if ($websiteContract->getMaintenanceContract()) {
                        $websiteData['maintenanceContract'] = [
                            'uuid' => $websiteContract->getMaintenanceContract()->getUuid(),
                            'startAt'=> $websiteContract->getMaintenanceContract()->getStartAt()->format("d-m-Y"),
                            'monthlyCost' => $websiteContract->getMaintenanceContract()->getMonthlyCost(),
                            'endAt' => $websiteContract->getMaintenanceContract()->getEndAt()->format("d-m-Y"),
                            'reccurence' => $websiteContract->getMaintenanceContract()->getReccurence(),
                            'createdAt' => $websiteContract->getMaintenanceContract()->getCreatedAt()->format("d-m-Y"),
                            'firstPaymentAt' => $websiteContract->getMaintenanceContract()->getFirstPaymentAt()->format("d-m-Y"),
                            'lastPaymentAt' => $websiteContract->getMaintenanceContract()->getLastPaymentAt()->format("d-m-Y"),
                            'nextPaymentAt' => $websiteContract->getMaintenanceContract()->getNextPaymentAt()->format("d-m-Y"),
                        ];
                    }

                    if ($websiteContract->getWebsiteVps()) {
                        $websiteData['websiteVps'] = [
                            'uuid' => $websiteContract->getWebsiteVps()->getUuid(),
                            'address'=> $websiteContract->getWebsiteVps()->getAddress(),
                            'username'=> $websiteContract->getWebsiteVps()->getUsername(),
                            'password'=> $websiteContract->getWebsiteVps()->getPassword(),
                            'port'=> $websiteContract->getWebsiteVps()->getPort(),
                            'publicKey'=> $websiteContract->getWebsiteVps()->getPublicKey(),
                            'updatedAt'=> $websiteContract->getWebsiteVps()->getUpdatedAt()->format("d-m-Y"),
                            'createdAt' => $websiteContract->getWebsiteVps()->getCreatedAt()->format("d-m-Y"),
                        ];
                    }

                    if ($websiteContract->getWebsiteMutualised()) {
                        $websiteData['websiteMutualised'] = [
                            'uuid' => $websiteContract->getWebsiteMutualised()->getUuid(),
                            'address'=> $websiteContract->getWebsiteMutualised()->getAddress(),
                            'username'=> $websiteContract->getWebsiteMutualised()->getUsername(),
                            'password'=> $websiteContract->getWebsiteMutualised()->getPassword(),
                            'port'=> $websiteContract->getWebsiteMutualised()->getPort(),
                            'updatedAt'=> $websiteContract->getWebsiteMutualised()->getUpdatedAt()->format("d-m-Y"),
                            'createdAt' => $websiteContract->getWebsiteMutualised()->getCreatedAt()->format("d-m-Y"),
                        ];
                    }

                    $array[] = $websiteData;
                }
            }

            return new JsonResponse($array, Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}