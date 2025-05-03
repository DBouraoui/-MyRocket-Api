<?php

namespace App\Controller;

use App\Entity\User;
use App\Event\WebsiteCredentialsEvent;
use App\Repository\WebsiteRepository;
use App\service\EmailService;
use App\service\WebsiteService;
use Psr\Cache\InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/user/website', name: 'app_user_website_')]
#[IsGranted('IS_AUTHENTICATED')]
final class WebsiteController extends AbstractController
{
    public const GET_ALL_WEBSITES = 'getAllWebsites';

    public function __construct(
        private readonly LoggerInterface        $logger,
        private readonly WebsiteRepository      $websiteRepository,
        private readonly WebsiteService         $websiteService,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Récupère tout les sites web de l'utilisateur courrant
     */
    #[Route(name: 'get', methods: ['GET'])]
    public function get(#[CurrentUser]User $user): JsonResponse {
        try {
                $websiteArray = $this->cache->get(self::GET_ALL_WEBSITES.$user->getUuid(), function (ItemInterface $item) use($user){
                    $item->expiresAfter(7200);
                    $websites =  $user->getWebsites()->toArray();
                   return $this->websiteService->normalizeWebsites($websites);
                });

                return $this->json($websiteArray, Response::HTTP_OK);

        } catch(\Exception $e) {
            $this->logger->error('Error fetching websites: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Error fetching websites: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Envoie les informations sensible des configuration  par email
     * @param $uuid
     * @param User $user
     * @return JsonResponse
     */
    #[route(path: '/configuration/{uuid}', name: 'configuration_uuid', methods: ['GET'])]
    public function sendWebsiteConfigByEmail($uuid,#[CurrentUser]User $user): JsonResponse {
        try {
            if (empty($uuid)) {
                Throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_NOT_FOUND);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $uuid]);

            if (!$website) {
                Throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            if ($website->getUser() !== $user) {
                Throw new \Exception(WebsiteService::USER_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            if (empty($website->getWebsiteVps()) && empty($website->getWebsiteMutualised())) {
                Throw new \Exception(WebsiteService::CONFIGURATION_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $configuration = [];

            if ($website->getWebsiteVps()) {
                $configuration = [
                    'address'=> $website->getWebsiteVps()->getAddress(),
                    'port'=> $website->getWebsiteVps()->getPort(),
                    'username'=> $website->getWebsiteVps()->getUsername(),
                    'password'=> $website->getWebsiteVps()->getPassword()
                ];
            }

            if ($website->getWebsiteMutualised()) {
                $configuration = [
                    'address'=> $website->getWebsiteMutualised()->getAddress(),
                    'port'=> $website->getWebsiteMutualised()->getPort(),
                    'username'=> $website->getWebsiteMutualised()->getUsername(),
                    'password'=> $website->getWebsiteMutualised()->getPassword()
                ];
            }

            if (empty($website->getWebsiteMutualised()) && empty($website->getWebsiteVps())) {
                Throw new \Exception(WebsiteService::CONFIGURATION_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

            $event = new WebsiteCredentialsEvent($user,$website,$configuration);
            $this->dispatcher->dispatch($event, WebsiteCredentialsEvent::NAME);

            return new JsonResponse(WebsiteService::SUCCESS_RESPONSE, Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error('Error fetching credentials: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}