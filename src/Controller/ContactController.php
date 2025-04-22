<?php

namespace App\Controller;

use App\DTO\contact\ContactCreateDTO;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/contact', name: 'app_contact')]
final class ContactController extends AbstractController
{
    public const GET_ALLCONTACTS = 'getAllContacts';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface        $logger,
        private readonly FilesystemOperator     $contactStorage,
        private readonly  ContactRepository     $contactRepository,
        private readonly ValidatorInterface     $validator, private readonly CacheInterface $cache,
    )
    {}

    #[Route(methods: ['POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function post(Request $request): JsonResponse
    {
        try {
            $data = [
                'firstname' => $request->request->get('firstname'),
                'lastname' => $request->request->get('lastname'),
                'companyName' => $request->request->get('companyName'),
                'email' => $request->request->get('email'),
                'title' => $request->request->get('title'),
                'description' => $request->request->get('description'),
                'tags'=> json_decode($request->request->get('tags'), true),
            ];

            $contactCreateDTO = ContactCreateDTO::fromArray($data);

            $violations = $this->validator->validate($contactCreateDTO);

            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[$violation->getPropertyPath()] = $violation->getMessage();
                }
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $contact = new Contact();
            $contactCreateDTO->firstname ? $contact->setFirstname($contactCreateDTO->firstname) : null;
            $contactCreateDTO->lastname ? $contact->setLastname($contactCreateDTO->lastname) : null;
            $contactCreateDTO->companyName ? $contact->setCompanyName($contactCreateDTO->companyName) : null;

            $contact->setEmail($contactCreateDTO->email);
            $contact->setTitle($contactCreateDTO->title);
            $contact->setDescription($contactCreateDTO->description);
            $contact->setTags($contactCreateDTO->tags);

            $arrayLinkPictures = [];

            $uploadedFile = $request->files->get('pictures');

            if ($uploadedFile) {
                if (filesize($uploadedFile) > 5000000) {
                    return $this->json(['success'=>false, 'message'=> 'The weight of image is too big'], Response::HTTP_EXPECTATION_FAILED);
                }

                $fileName = uniqid('contact_') . '.jpg';
                $fileContent = file_get_contents($uploadedFile->getPathname());
                $this->contactStorage->write($fileName, $fileContent);
                $arrayLinkPictures[] = $fileName;
                $this->logger->debug('Saved file: ' . $fileName);

                if (!empty($arrayLinkPictures)) {
                    $contact->setPictures($arrayLinkPictures);
                }
            }

            $this->entityManager->persist($contact);
            $this->entityManager->flush();

            $this->cache->delete(self::GET_ALLCONTACTS);

            return $this->json(
                ['success' => true, 'contactId' => $contact->getId()],
                Response::HTTP_CREATED
            );

        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la création du contact: " . $e->getMessage());
            return $this->json(['error' => 'Erreur serveur: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(methods: ['DELETE'])]
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

    #[Route(methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
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
                'app_contactapp_contact_image_get',
                ['filename' => $filename],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération de l\'URL de l\'image: ' . $e->getMessage());
            return null;
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
    #[Route('/api/contact/image/{filename}', name: 'app_contact_image_get', methods: ['GET'])]
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
     * Valide si une image JPEG est valide (méthode existante)
     */
    private function isValidJpegImage(string $base64Image): bool
    {
        if (strpos($base64Image, 'data:image/jpeg;base64,') === 0) {
            $base64Image = substr($base64Image, strlen('data:image/jpeg;base64,'));
        }

        $imageData = base64_decode($base64Image, true);

        if ($imageData === false) {
            return false;
        }

        $image = @imagecreatefromstring($imageData);

        if ($image === false) {
            return false;
        }

        $isJpeg = ord($imageData[0]) === 0xFF && ord($imageData[1]) === 0xD8 &&
            ord($imageData[strlen($imageData) - 2]) === 0xFF && ord($imageData[strlen($imageData) - 1]) === 0xD9;

        imagedestroy($image);

        return $isJpeg;
    }
}
