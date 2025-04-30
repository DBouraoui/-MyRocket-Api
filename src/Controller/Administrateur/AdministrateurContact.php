<?php

namespace App\Controller\Administrateur;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/administrateur/contact', name: 'api_administrateur_contact')]
#[IsGranted('ROLE_ADMIN')]
class AdministrateurContact extends AbstractController
{
    public const GET_ALLCONTACTS = 'getAllContacts';
    public function __construct(private readonly ContactRepository $contactRepository, private readonly FilesystemOperator $contactStorage, private readonly LoggerInterface $logger, private readonly EntityManagerInterface $entityManager, private readonly CacheInterface $cache,)
    {
    }

    #[Route(name:'_delete',methods: ['DELETE'])]
    public function delete(Request $request): JsonResponse
    {
        try {
            $contactUuid = $request->query->get('uuid');

            if (empty($contactUuid)) {
                return $this->json(
                    ['success' => false, 'message' => 'UUID du contact requis'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $contact = $this->contactRepository->findOneBy(['uuid' => $contactUuid]);

            if (empty($contact)) {
                return $this->json(
                    ['success' => false, 'message' => 'Contact non trouvé'],
                    Response::HTTP_NOT_FOUND
                );
            }

            $pictures = $contact->getPictures();
            $deletedFiles = [];
            $failedFiles = [];

            foreach ($pictures as $picture) {
                try {
                    $filePath = $picture;

                    if ($this->contactStorage->fileExists($filePath)) {
                        $this->contactStorage->delete($filePath);
                        $deletedFiles[] = $filePath;
                    } else {
                        $failedFiles[] = $filePath;
                        $this->logger->warning("Fichier non trouvé lors de la suppression : {$filePath}");
                    }
                } catch (\Exception $e) {
                    $failedFiles[] = $picture;
                    $this->logger->error("Erreur lors de la suppression du fichier {$picture}: " . $e->getMessage());
                }
            }

            $this->entityManager->remove($contact);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Contact supprimé',
                'deletedFiles' => $deletedFiles,
                'failedFiles' => $failedFiles
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la suppression du contact: ' . $e->getMessage());
            return $this->json(
                ['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route(name:'_get',methods: ['GET'])]
    public function get(Request $request): JsonResponse
    {
        try {
            $oneContactByUuid = $request->query->get('uuid');
            $all = $request->query->get('all');
            $withImageUrls = $request->query->getBoolean('withImageUrls', true);

            if (!empty($oneContactByUuid) && empty($all)) {

                $contact = $this->cache->get(self::GET_ALLCONTACTS,function (ItemInterface $item) use($oneContactByUuid) {
                    $item->expiresAfter(3600);
                    return $this->contactRepository->findOneBy(['uuid' => $oneContactByUuid]);
                });

                if (empty($contact)) {
                    return $this->json(
                        ['success' => false, 'message' => 'Contact non trouvé'],
                        Response::HTTP_NOT_FOUND
                    );
                }

                return $this->json([
                    'success' => true,
                    'data' => $this->normalizeContact($contact, $withImageUrls)
                ], Response::HTTP_OK);
            }

            if (!empty($all) && empty($oneContactByUuid)) {
                $contacts = $this->contactRepository->findAll();
                return $this->json([
                    'success' => true,
                    'data' => $this->normalizeContacts($contacts, $withImageUrls)
                ], Response::HTTP_OK);
            }

            return $this->json(
                ['success' => false, 'message' => 'Aucune donnée ne correspond'],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['error' => 'Erreur serveur'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Normalise un contact et inclut les URLs des images si demandé
     */
    private function normalizeContact(Contact $contact, bool $withImageUrls = false): array
    {
        $normalizedContact = [
            'uuid' => $contact->getUuid(),
            'firstname' => $contact->getFirstname(),
            'lastname' => $contact->getLastname(),
            'companyName' => $contact->getCompanyName(),
            'email' => $contact->getEmail(),
            'title' => $contact->getTitle(),
            'description' => $contact->getDescription(),
            'tags' => $contact->getTags(),
            'createdAt' => $contact->getCreatedAt()->format('d-m-Y H:i:s'),
        ];

        // Récupérer les noms des fichiers d'images
        $pictureFilenames = $contact->getPictures() ?? [];

        if ($withImageUrls && !empty($pictureFilenames)) {
            $pictureUrls = [];

            foreach ($pictureFilenames as $filename) {
                $imageUrl = $this->getContactImageUrl($filename);
                if ($imageUrl) {
                    $pictureUrls[] = [
                        'filename' => $filename,
                        'url' => $imageUrl
                    ];
                }
            }

            $normalizedContact['pictures'] = $pictureUrls;
        } else {
            $normalizedContact['pictures'] = $pictureFilenames;
        }

        return $normalizedContact;
    }

    /**
     * Normalise un tableau de contacts
     */
    private function normalizeContacts(array $contacts, bool $withImageUrls = false): array
    {
        $contactArray = [];
        foreach ($contacts as $contact) {
            $contactArray[] = $this->normalizeContact($contact, $withImageUrls);
        }
        return $contactArray;
    }

    /**
     * Route pour servir les images des contacts
     */
    #[Route('/api/contact/image/{filename}', name: '_image_get', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function getContactImage(string $filename): Response
    {
        try {
            // Vérifier si le fichier existe
            if (!$this->contactStorage->fileExists($filename)) {
                return new Response('Image non trouvée', Response::HTTP_NOT_FOUND);
            }

            // Récupérer le contenu du fichier
            $fileContent = $this->contactStorage->read($filename);

            // Déterminer le type MIME
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($fileContent) ?: 'application/octet-stream';

            // Créer la réponse avec les en-têtes appropriés
            $response = new Response($fileContent);
            $response->headers->set('Content-Type', $mimeType);
            $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');

            // Ajouter des en-têtes de cache pour améliorer les performances
            $response->setPublic();
            $response->setMaxAge(3600); // Cache pour 1 heure
            $response->headers->addCacheControlDirective('must-revalidate');

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération de l\'image de contact: ' . $e->getMessage());
            return new Response('Erreur lors de la récupération de l\'image', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Génère une URL pour l'image d'un contact
     */
    private function getContactImageUrl(string $filename): ?string
    {
        try {
            if (empty($filename)) {
                return null;
            }

            // Vérifier si le fichier existe
            if (!$this->contactStorage->fileExists($filename)) {
                $this->logger->warning('Le fichier image de contact n\'existe pas: ' . $filename);
                return null;
            }

            // Générer une URL pour l'image
            return $this->generateUrl(
                'api_administrateur_contact_image_get',
                ['filename' => $filename],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération de l\'URL de l\'image: ' . $e->getMessage());
            return null;
        }
    }
}