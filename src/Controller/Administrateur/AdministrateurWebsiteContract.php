<?php

namespace App\Controller\Administrateur;

use App\Event\WebsiteContractEvent;
use App\Repository\UserRepository;
use App\Repository\WebsiteRepository;
use App\service\EmailService;
use App\service\WebsiteService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/api/administrateur/website-contract', name: 'api_administrateur_website_contract')]
#[IsGranted('ROLE_ADMIN')]
class AdministrateurWebsiteContract extends AbstractController
{
    public const POST_REQUIRE_FIELDS = ['uuidWebsite', 'annualCost', 'tva', 'reccurence', 'prestation', 'firstPaymentAt', 'lastPaymentAt', 'nextPaymentAt'];

    public function __construct
    (
        private WebsiteService             $websiteService,
        private readonly WebsiteRepository $websiteRepository,
        private readonly LoggerInterface   $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly EmailService $emailService
    )
    {

    }

    /**
     * Créer un contrat pour un site web
     * @param Request $request
     * @return JsonResponse
     */
    #[Route(name: '_post', methods: ['POST'])]
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

           $websiteContract = $this->websiteService->createWebsiteContract($data, $user,$website);

            $event = new WebsiteContractEvent($user,$websiteContract);
            $this->dispatcher->dispatch($event, WebsiteContractEvent::NAME);

            return new JsonResponse(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse($e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime un contrat d'un site web
     * @param $uuid
     * @return JsonResponse
     */
    #[Route(path: '/{uuid}', name: '_delte', methods: ['DELETE'])]
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
}
