<?php

namespace App\Controller;

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
}
