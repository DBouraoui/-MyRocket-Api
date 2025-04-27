<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\WebsiteRepository;
use App\service\WebsiteService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/website/contract', name: 'app_website_contract')]
final class WebsiteContractController extends AbstractController
{
    public const POST_REQUIRE_FIELDS = ['uuidWebsite', 'annualCost', 'tva', 'reccurence', 'prestation', 'firstPaymentAt', 'lastPaymentAt', 'nextPaymentAt'];

    public function __construct(private readonly LoggerInterface $logger, private readonly WebsiteService $websiteService, private readonly WebsiteRepository $websiteRepository, private readonly EntityManagerInterface $entityManager, private readonly UserRepository $userRepository)
    {
    }

    #[Route(name: '_post', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function post(Request $request): JsonResponse
    {
        try {
             $data = json_decode($request->getContent(), true);

             if (empty($data)) {
                 Throw new \Exception(WebsiteService::EMPTY_DATA);
             }

             $this->checkRequiredFields(self::POST_REQUIRE_FIELDS, $data);

             if (empty($data['uuidWebsite'])) {
                 Throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_BAD_REQUEST);
             }

             $website = $this->websiteRepository->findOneBy(['uuid'=>$data['uuidWebsite']]);

             if (empty($website)) {
                 Throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_BAD_REQUEST);
             }

            $user = $website->getUser();

             if (empty($user)) {
                 Throw new \Exception(WebsiteService::USER_NOT_FOUND, Response::HTTP_BAD_REQUEST);
             }

             $contract = $website->getWebsiteContract();

             if (!empty($contract)) {
                 Throw new \Exception(WebsiteService::WEBSITE_CONTRACT_ALREADY_EXIST, Response::HTTP_BAD_REQUEST);
             }

             $this->websiteService->createWebsiteContract($data, $user,$website);

            return new JsonResponse(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse($e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/{uuid}', name: '_delte', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete($uuid) {
        try {
            if (empty($uuid)) {
                Throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_BAD_REQUEST);
            }

            $website = $this->websiteRepository->findOneBy(['uuid'=>$uuid]);

            if (empty($website)) {
                Throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_BAD_REQUEST);
            }

            $websiteContract = $website->getWebsiteContract();

            if (empty($websiteContract)) {
                Throw new \Exception(WebsiteService::WEBSITE_CONTRACT_NOT_FOUND, Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->remove($websiteContract);
            $this->entityManager->flush();

            return new JsonResponse(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse($e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/me', name: '_get_my_contract', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function getMyContract(#[CurrentUser]User $user) {
        try {
           $websitesContract = $user->getWebsiteContract()->toArray();

            return new JsonResponse($this->websiteService->normalizeWebsitesContracts($websitesContract), Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse($e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[route(name: '_get', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function get(Request $request) {
        try {
            $all = $request->query->get('all', false); //uuidUser
            $one = $request->query->get('one', false); // uuidWebsite

            if (empty($one) && empty($all)) {
                Throw new \Exception(WebsiteService::PARAMETERS_NOT_FOUND, Response::HTTP_BAD_REQUEST);
            }

            if (!empty($one)) {
                $website = $this->websiteRepository->findOneBy(['uuid'=>$one]);

                if (empty($website)) {
                    Throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_BAD_REQUEST);
                }

                $websiteContract = $website->getWebsiteContract();

                if (empty($websiteContract)) {
                    Throw new \Exception(WebsiteService::WEBSITE_CONTRACT_NOT_FOUND, Response::HTTP_BAD_REQUEST);
                }

                return new JsonResponse($this->websiteService->normalizeWebsiteContract($websiteContract), Response::HTTP_OK);
            }

            if (!empty($all)) {

                $user = $this->userRepository->findOneBy(['uuid'=>$all]);

                if (empty($user)) {
                    Throw new \Exception(WebsiteService::USER_NOT_FOUND, Response::HTTP_BAD_REQUEST);
                }

                $websiteContracts = $user->getWebsiteContract()->toArray();

                if (empty($websiteContracts)) {
                    Throw new \Exception(WebsiteService::WEBSITE_CONTRACT_NOT_FOUND, Response::HTTP_BAD_REQUEST);
                }

                return new JsonResponse($this->websiteService->normalizeWebsitesContracts($websiteContracts), Response::HTTP_OK);
            }

            return new JsonResponse(WebsiteService::ERROR_RESPONSE, Response::HTTP_NOT_FOUND);
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse($e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[route(path: '/get/all', name: '_getall', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function getAllInformation(#[CurrentUser]User $user) {
        try {
            $website = $user->getWebsites();
            $array = [];
            foreach ($website as $websiteContract) {
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

                $array[] = $websiteData;
            }

            return new JsonResponse($array, Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse($e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[route(path: '/get/all/informations', name: '_getallinformation', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
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
