<?php

namespace App\Controller;

use App\Entity\WebsiteVps;
use App\Repository\WebsiteRepository;
use App\Repository\WebsiteVpsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/website/vps', name: 'app_websitevps')]
final class WebsitevpsController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger, private readonly WebsiteRepository $websiteRepository, private readonly EntityManagerInterface $entityManager, private readonly WebsiteVpsRepository $websiteVpsRepository)
    {
    }

    #[Route(name: 'app_website_vps', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                Throw new \Exception('Les données reçus sont vide',Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $requiredFields = ['uuidWebsite', 'username', 'password', 'address', 'port', 'publicKey'];
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

            $websitevps = new Websitevps();
            $websitevps->setUsername($data['username']);
            $websitevps->setPassword($data['password']);
            $websitevps->setAddress($data['address']);
            $websitevps->setPort($data['port']);
            $websitevps->setPublicKey($data['publicKey']);
            $websitevps->setWebsite($website);

            $this->entityManager->persist($websitevps);
            $this->entityManager->flush();

            return $this->json([],Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
