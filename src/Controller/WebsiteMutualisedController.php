<?php

namespace App\Controller;

use App\Entity\WebsiteMutualised;
use App\Repository\WebsiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/website/mutualised', name: 'app_website_mutualised')]
final class WebsiteMutualisedController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger, private readonly WebsiteRepository $websiteRepository, private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route( name: 'app_website_mutualised', methods: [ 'POST' ])]
    public function index(Request $request): JsonResponse
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
}
