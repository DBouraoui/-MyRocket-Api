<?php

namespace App\Controller;

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

#[Route('/api/website/mutualised', name: 'app_website_mutualised')]
final class WebsiteMutualisedController extends AbstractController
{
    public const POST_REQUIRED_FILDS =['uuidWebsite', 'username', 'password', 'address', 'port'];
    public const PUT_ALLOW_FIELDS = ['username', 'password', 'address', 'port'];

    public function __construct
    (
        private readonly LoggerInterface        $logger,
        private readonly WebsiteRepository      $websiteRepository,
        private readonly EntityManagerInterface $entityManager, private readonly WebsiteService $websiteService,
    )
    {
    }

    #[Route( name: 'app_website_mutualised_post', methods: [ 'POST' ])]
    #[IsGranted("ROLE_ADMIN")]
    public function post(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                Throw new \Exception(WebsiteService::EMPTY_DATA,Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->checkRequiredFields(self::POST_REQUIRED_FILDS,$data);

            $website = $this->websiteRepository->findOneBy(['uuid' => $data['uuidWebsite']]);

            if (empty($website)) {
                Throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (!empty($website->getWebsiteVps()) || !empty($website->getWebsiteMutualised())) {
                Throw new \Exception(WebsiteService::CONFIGURATION_ALREADY_EXISTS, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (empty($website)) {
                Throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND,Response::HTTP_NOT_FOUND);
            }

           $mutualisedWebsite = $this->websiteService->createMutualisedConfiguration($data,$website);

            return $this->json(WebsiteService::SUCCESS_RESPONSE,Response::HTTP_OK);

        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()],$e->getCode());
        }
    }

     /**
      * Met à jour une configuration de site mutualisé existante
      */
     #[Route( name: 'app_website_mutualised_put', methods: [ 'PUT' ])]
     #[IsGranted("ROLE_ADMIN")]
     public function put(Request $request): JsonResponse
     {
         try {
             $data = json_decode($request->getContent(), true);

             if (empty($data)) {
                 throw new \Exception(WebsiteService::EMPTY_DATA, Response::HTTP_UNPROCESSABLE_ENTITY);
             }

             if (empty($data['uuidWebsite'])) {
                 throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_UNPROCESSABLE_ENTITY);
             }

             $website = $this->websiteRepository->findOneBy(['uuid' => $data['uuidWebsite']]);

             if (empty($website)) {
                 throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
             }

             $websiteMutualised = $website->getWebsiteMutualised();

             if (empty($websiteMutualised)) {
                 Throw new \Exception(WebsiteService::CONFIGURATION_NOT_FOUND, Response::HTTP_NOT_FOUND);
             }

             foreach (self::PUT_ALLOW_FIELDS as $field) {
                 if (isset($data[$field])) {
                     $setter = 'set' . ucfirst($field);
                     if (method_exists($websiteMutualised, $setter)) {
                         $websiteMutualised->$setter($data[$field]);
                     }
                 }
             }

             $this->entityManager->flush();

             return $this->json(
                 WebsiteService::SUCCESS_RESPONSE
             , Response::HTTP_OK);
         } catch (\Exception $e) {
             $this->logger->error('Erreur lors de la mise à jour: ' . $e->getMessage(), [
                 'trace' => $e->getTraceAsString()
             ]);
             return new JsonResponse([
                 'success' => $e->getMessage(),
             ],$e->getCode());
         }
     }

    /**
     * Supprime une configuration mutualisé via sont uuid
     */
    #[Route(path: '/{uuid}' ,name: 'app_website_mutualised_delete', methods: [ 'DELETE' ])]
    #[IsGranted("ROLE_ADMIN")]
    public function delete($uuid) {
        try {
            if (empty($uuid)) {
                Throw new \Exception(WebsiteService::EMPTY_UUID, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

           $website = $this->websiteRepository->findOneBy(['uuid' => $uuid]);

            if (empty($website)) {
                Throw new \Exception(WebsiteService::WEBSITE_NOT_FOUND, Response::HTTP_NOT_FOUND);
            }

           $websiteMutualised = $website->getWebsiteMutualised();

            if (empty($websiteMutualised)) {
                Throw new \Exception(WebsiteService::CONFIGURATION_NOT_FOUND,Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($websiteMutualised);
            $this->entityManager->flush();

            return $this->json(WebsiteService::SUCCESS_RESPONSE,Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()],$e->getCode());
        }
    }
}
