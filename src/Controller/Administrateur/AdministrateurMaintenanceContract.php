<?php

namespace App\Controller\Administrateur;

use App\Repository\MaintenanceContractRepository;
use App\Repository\UserRepository;
use App\Repository\WebsiteRepository;
use App\service\MaintenanceContractService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path:'/api/administrateur/maintenance-contract',name: 'api_administrateur_maintenance_contract')]
#[IsGranted('ROLE_ADMIN')]
class AdministrateurMaintenanceContract extends AbstractController
{
    public const POST_REQUIRED_FIELDS = ['monthlyCost', 'uuidWebsite', 'firstPaymentAt','reccurence', 'startAt', 'endAt'];
    public const PUT_ALLOW_FIELDS = ['monthlyCost', 'reccurence', 'endAt'];
    public function __construct(private readonly WebsiteRepository $websiteRepository, private readonly MaintenanceContractService $maintenanceContractService, private readonly LoggerInterface $logger, private readonly MaintenanceContractRepository $maintenanceContractRepository, private readonly EntityManagerInterface $entityManager, private readonly UserRepository $userRepository)
    {
    }

    #[Route(name: '_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                Throw new \Exception(MaintenanceContractService::EMPTY_DATA, Response::HTTP_BAD_REQUEST);
            }

            $this->checkRequiredFields(self::POST_REQUIRED_FIELDS, $data);


            $website = $this->websiteRepository->findOneBy(['uuid'=>$data['uuidWebsite']]);

            if (empty($website)) {
                Throw new \Exception(MaintenanceContractService::WEBSITE_NOT_FOUND,Response::HTTP_NOT_FOUND);
            }

            $userWebsite = $website->getUser();

            if ($website->getMaintenanceContract()) {
                Throw new \Exception(MaintenanceContractService::MAINTENANCE_CONTRACT_ALREADY_EXISTS,Response::HTTP_UNAUTHORIZED);
            }

            $this->maintenanceContractService->createService($userWebsite, $website, $data);

            return new JsonResponse(MaintenanceContractService::SUCCESS_RESPONSE,Response::HTTP_OK);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            return new JsonResponse(['error' => $exception->getMessage()], $exception->getCode());
        }
    }

    #[Route(name: '_put', methods: ['PUT'])]
    public function put(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                Throw new \Exception(MaintenanceContractService::EMPTY_DATA, Response::HTTP_BAD_REQUEST);
            }

            if (empty($data['uuidMaintenanceContract'])) {
                Throw new \Exception(MaintenanceContractService::EMPTY_UUID,Response::HTTP_NOT_FOUND);
            }

            $mainteanceContract = $this->maintenanceContractRepository->findOneBy(['uuid'=>$data['uuidMaintenanceContract']]);

            if (empty($mainteanceContract)) {
                Throw new \Exception(MaintenanceContractService::MAINTENANCE_CONTRACT_NOT_FOUND,Response::HTTP_NOT_FOUND);
            }


            foreach (self::PUT_ALLOW_FIELDS as $field) {
                if ($field == 'endAt') {
                    $data[$field] = \DateTimeImmutable::createFromFormat('Y-m-d', $data['endAt']) ?: new \DateTimeImmutable($data['endAt']);
                }
                if (isset($data[$field])) {
                    $setter = 'set' . ucfirst($field);
                    if (method_exists($mainteanceContract, $setter)) {
                        $mainteanceContract->$setter($data[$field]);
                    }
                }
            }

            $this->entityManager->flush();

            return new JsonResponse(MaintenanceContractService::SUCCESS_RESPONSE,Response::HTTP_OK);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            return new JsonResponse(['error' => $exception->getMessage()],Response::HTTP_FOUND);
        }
    }

    #[route(path: '/{uuid}',name: '_delete_uuid', methods: ['DELETE'])]
    public function delete($uuid): JsonResponse
    {
        try {
            if (empty($uuid)) {
                Throw new \Exception(MaintenanceContractService::EMPTY_UUID,Response::HTTP_NOT_FOUND);
            }

            $maintenanceContract = $this->maintenanceContractRepository->findOneBy(['uuid'=>$uuid]);

            if (empty($maintenanceContract)) {
                Throw new \Exception(MaintenanceContractService::MAINTENANCE_CONTRACT_NOT_FOUND,Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($maintenanceContract);
            $this->entityManager->flush();

            return new JsonResponse(MaintenanceContractService::SUCCESS_RESPONSE,Response::HTTP_OK);
        } catch(\Exception $exception) {
            $this->logger->error($exception->getMessage());
            return new JsonResponse(['error' => $exception->getMessage()], $exception->getCode());
        }
    }


    #[route(name: '_get', methods: ['GET'])]
    public function get(Request $request): JsonResponse {
        try {
            $one = $request->query->get('one', false);
            $all = $request->query->get('all', false);

            if (empty($one) && empty($all)) {
                Throw new \Exception(MaintenanceContractService::PARAMETERS_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            if (!empty($one)) {
                $website = $this->websiteRepository->findOneBy(['uuid'=>$one]);
                $maintenanceContract = $website->getMaintenanceContract();

                if (empty($maintenanceContract)) {
                    Throw new \Exception(MaintenanceContractService::MAINTENANCE_CONTRACT_NOT_FOUND,Response::HTTP_NOT_FOUND);
                }

                return new JsonResponse($this->maintenanceContractService->normalizeMaintenanceContract($maintenanceContract),Response::HTTP_OK);
            }

            if (!empty($all)) {
                $userMainteananceContract = $this->userRepository->findOneBy(['uuid'=>$all]);

                if (empty($userMainteananceContract)) {
                    Throw new \Exception(MaintenanceContractService::USER_NOT_FOUND,Response::HTTP_NOT_FOUND);
                }

                $maintenanceContracts = $userMainteananceContract->getMaintenanceContracts()->toArray();

                return new JsonResponse($this->maintenanceContractService->normalizeMaintenancesContracts($maintenanceContracts),Response::HTTP_OK);
            }

            return new JsonResponse(MaintenanceContractService::ERROR_RESPONSE,Response::HTTP_NOT_FOUND);
        } catch(\Exception $exception) {
            $this->logger->error($exception->getMessage());
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

}