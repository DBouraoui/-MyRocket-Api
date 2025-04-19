<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Website;
use App\Entity\WebsiteMutualised;
use App\Entity\WebsiteVps;
use App\Repository\UserRepository;
use App\Repository\WebsiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/website', name: 'app_website_')]
final class WebsiteController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface        $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly WebsiteRepository      $websiteRepository,
        private readonly ValidatorInterface     $validator, private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Crée un nouveau site web
     */
    #[Route(name: 'post', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                return $this->json(['success' => false, 'message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
            }

            $requiredFields = ['title', 'url', 'description', 'status', 'type'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->json(['success' => false, 'message' => "Missing required field: $field"], Response::HTTP_BAD_REQUEST);
                }
            }

            $user = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);

            $website = new Website();
            $website->setTitle($data['title']);
            $website->setUrl($data['url']);
            $website->setDescription($data['description']);
            $website->setStatus($data['status']);
            $website->setType($data['type']);
            $website->setUser($user);

            switch ($data['type']) {
                case 'mutualised':
                    $requiredMutualisedFields = ['address', 'port', 'password', 'username'];
                    foreach ($requiredMutualisedFields as $field) {
                        if (!isset($data[$field])) {
                            return $this->json(['success' => false, 'message' => "Missing required field for mutualised type: $field"], Response::HTTP_BAD_REQUEST);
                        }
                    }
                    $this->setCredentialsMutualised($website, $data);
                    break;

                case 'vps':
                    $requiredVpsFields = ['address', 'port', 'password', 'publicKey', 'ssh', 'username'];
                    foreach ($requiredVpsFields as $field) {
                        if (!isset($data[$field])) {
                            return $this->json(['success' => false, 'message' => "Missing required field for VPS type: $field"], Response::HTTP_BAD_REQUEST);
                        }
                    }
                    $this->setCredentialsVps($website, $data);
                    break;

                default:
                    break;
            }

            // Valider l'entité avant de la persister
            $errors = $this->validator->validate($website);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['success' => false, 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($website);
            $this->entityManager->flush();

            return $this->json(['success' => true], Response::HTTP_CREATED);
        } catch(\Exception $e) {
            $this->logger->error('Error creating website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère un ou tous les sites web
     */
    #[Route(name: 'get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function get(Request $request, #[CurrentUser]User $user): JsonResponse {
        try {
            $all = $request->query->get('all', false);
            $uuid = $request->query->get('uuid', null);

            if ($all) {
                $websites = $user->getWebsites()->toArray();
                $websiteArray = $this->normalizeWebsites($websites);

                return $this->json($websiteArray, Response::HTTP_OK);
            } elseif ($uuid) {
                $website = $this->websiteRepository->findOneBy(['uuid' => $uuid]);

                if (!$website) {
                    return $this->json(['success' => false, 'message' => 'Website not found'], Response::HTTP_NOT_FOUND);
                }

                $websiteArray = $this->normalizeWebsite($website);
                return $this->json($websiteArray, Response::HTTP_OK);
            } else {
                return $this->json(['success' => false, 'message' => 'Missing parameter: all or uuid'], Response::HTTP_BAD_REQUEST);
            }
        } catch(\Exception $e) {
            $this->logger->error('Error fetching websites: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Met à jour un site web existant
     */
    #[Route(name: 'put', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data)) {
                return $this->json(['success' => false, 'message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['uuid'])) {
                return $this->json(['success' => false, 'message' => 'Missing required field: uuid'], Response::HTTP_BAD_REQUEST);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $data['uuid']]);

            if (!$website) {
                return $this->json(['success' => false, 'message' => 'Website not found'], Response::HTTP_NOT_FOUND);
            }

            $allowedProperties = [
                'title' => 'setTitle',
                'url' => 'setUrl',
                'description' => 'setDescription',
                'status' => 'setStatus',
                'type' => 'setType'
            ];

            foreach ($allowedProperties as $property => $setter) {
                if (isset($data[$property])) {
                    $website->$setter($data[$property]);
                }
            }

            // Valider l'entité avant de la mettre à jour
            $errors = $this->validator->validate($website);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['success' => false, 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->flush();

            return $this->json(['success' => true], Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error('Error updating website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime un site web
     */
    #[Route(path: '/{uuid}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(string $uuid): JsonResponse {
        try {
            if (empty($uuid)) {
                return $this->json(['success' => false, 'message' => 'Invalid uuid'], Response::HTTP_BAD_REQUEST);
            }

            $website = $this->websiteRepository->findOneBy(['uuid' => $uuid]);

            if (!$website) {
                return $this->json(['success' => false, 'message' => 'Website not found'], Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($website);
            $this->entityManager->flush();

            return $this->json(['success' => true], Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error('Error deleting website: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Normalise une entité Website en tableau
     */
    private function normalizeWebsite(Website $website): array {
        return [
            "uuid" => $website->getUuid(),
            "title" => $website->getTitle(),
            "url" => $website->getUrl(),
            "description" => $website->getDescription(),
            "status" => $website->getStatus(),
            "type" => $website->getType(),
            "createdAt" => $website->getCreatedAt()?->format('m-d-Y'),
            "updatedAt" => $website->getUpdatedAt()?->format('m-d-Y'),
        ];
    }

    /**
     * Normalise un tableau d'entités Website en tableau de tableaux
     */
    private function normalizeWebsites(array $websites): array {
        $websitesArray = [];
        foreach ($websites as $website) {
            $websitesArray[] = $this->normalizeWebsite($website);
        }
        return $websitesArray;
    }

    /**
     * Configure les credentials VPS pour un website
     *
     * @param Website $website L'entité website à configurer
     * @param array $data Les données envoyées par le client
     * @throws \Exception Si une erreur se produit lors de la configuration
     */
    private function setCredentialsVps(Website $website, array $data): void
    {
        try {
            $websiteVps = new WebsiteVps();
            $websiteVps->setAddress($data['address']);
            $websiteVps->setPort($data['port']);
            $websiteVps->setPassword($data['password']);
            $websiteVps->setPublicKey($data['publicKey']);
            $websiteVps->setSsh($data['ssh']);
            $websiteVps->setUsername($data['username']);

            // Persist the WebsiteVps entity first if needed
            $this->entityManager->persist($websiteVps);

            // Assign the WebsiteVps to the Website
            $website->setWebsiteVps($websiteVps);
        } catch(\Exception $e) {
            $this->logger->error('Error creating VPS credentials: ' . $e->getMessage());
            throw new \Exception('Error creating VPS credentials: ' . $e->getMessage());
        }
    }

    /**
     * Configure les credentials Mutualisées pour un website
     *
     * @param Website $website L'entité website à configurer
     * @param array $data Les données envoyées par le client
     * @throws \Exception Si une erreur se produit lors de la configuration
     */
    private function setCredentialsMutualised(Website $website, array $data): void
    {
        try {
            $websiteMutualised = new WebsiteMutualised();
            $websiteMutualised->setAddress($data['address']);
            $websiteMutualised->setPort($data['port']);
            $websiteMutualised->setPassword($data['password']);
            $websiteMutualised->setUsername($data['username']);

            // Persist the WebsiteMutualised entity first if needed
            $this->entityManager->persist($websiteMutualised);

            // Assign the WebsiteMutualised to the Website
            $website->setWebsiteMutualised($websiteMutualised);
        } catch(\Exception $e) {
            $this->logger->error('Error creating mutualised credentials: ' . $e->getMessage());
            throw new \Exception('Error creating mutualised credentials: ' . $e->getMessage());
        }
    }
}