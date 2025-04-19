<?php

namespace App\Controller;

use App\Entity\WebsiteMutualised;
use App\Repository\WebsiteMutualisedRepository;
use App\Repository\WebsiteRepository;
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
    public function __construct(private readonly LoggerInterface $logger, private readonly WebsiteRepository $websiteRepository, private readonly EntityManagerInterface $entityManager, private readonly WebsiteMutualisedRepository $websiteMutualisedRepository)
    {
    }

    #[Route( name: 'app_website_mutualised_post', methods: [ 'POST' ])]
    #[IsGranted("ROLE_ADMIN")]
    public function post(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                Throw new \Exception('Les données reçus sont vide',Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $requiredFields = ['uuidWebsite', 'username', 'password', 'address', 'port'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    Throw new \Exception("Le champ '{$field}' est requis", Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $data['uuidWebsite']]);

            if (empty($website)) {
                Throw new \Exception("Aucun website pour trouvé", Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (!empty($website->getWebsiteVps()) || !empty($website->getWebsiteMutualised())) {
                Throw new \Exception("Une configuration existe déja pour ce  site web", Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (empty($website)) {
                Throw new \Exception('Aucun website trouvés',Response::HTTP_NOT_FOUND);
            }

            $websiteMutualised = new WebsiteMutualised();
            $websiteMutualised->setUsername($data['username']);
            $websiteMutualised->setPassword($data['password']);
            $websiteMutualised->setAddress($data['address']);
            $websiteMutualised->setPort($data['port']);
            $websiteMutualised->setWebsite($website);

            $this->entityManager->persist($websiteMutualised);
            $this->entityManager->flush();

            return $this->json([],Response::HTTP_OK);

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
                 throw new \Exception("Les données reçues sont vides", Response::HTTP_UNPROCESSABLE_ENTITY);
             }

             if (empty($data['uuidWebsite'])) {
                 throw new \Exception("Le champ 'uuidWebsite' est requis", Response::HTTP_UNPROCESSABLE_ENTITY);
             }

             $website = $this->websiteRepository->findOneBy(['uuid' => $data['uuidWebsite']]);

             if (empty($website)) {
                 throw new \Exception("Aucun website trouvé", Response::HTTP_NOT_FOUND);
             }

             $websiteMutualised = $website->getWebsiteMutualised();
             if (empty($websiteMutualised)) {
                 Throw new \Exception("Aucune configuration mutualisé trouver pour ce website", Response::HTTP_NOT_FOUND);
             }

             $allowedFields = ['username', 'password', 'address', 'port'];

             foreach ($allowedFields as $field) {
                 if (isset($data[$field])) {
                     $setter = 'set' . ucfirst($field);
                     if (method_exists($websiteMutualised, $setter)) {
                         $websiteMutualised->$setter($data[$field]);
                     }
                 }
             }

             $this->entityManager->flush();

             return $this->json([
                 'success' => true,
                 'message' => 'Configuration mise à jour avec succès'
             ], Response::HTTP_OK);
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
                Throw new \Exception('Le uuid est vide', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

           $website = $this->websiteRepository->findOneBy(['uuid' => $uuid]);

            if (empty($website)) {
                Throw new \Exception("Aucun website trouver");
            }

           $websiteMutualised = $website->getWebsiteMutualised();

            if (empty($websiteMutualised)) {
                Throw new \Exception("Aucune configuration mutualised trouvé",Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($websiteMutualised);
            $this->entityManager->flush();

            return $this->json(['success'=>true],Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()],$e->getCode());
        }
    }
}
