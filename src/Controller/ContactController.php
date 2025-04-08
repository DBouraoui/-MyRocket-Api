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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/contact', name: 'app_contact')]
final class ContactController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface        $logger,
        private readonly FilesystemOperator     $contactStorage,
        private readonly  ContactRepository     $contactRepository, private readonly ValidatorInterface $validator,
    )
    {}

    #[Route(methods: ['POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function post(Request $request): JsonResponse
    {
        try {
          $data = json_decode($request->getContent(), true);

          $contactCreateDTO = ContactCreateDTO::fromArray($data);

          $violations = $this->validator->validate($contactCreateDTO);

          if (count($violations) > 0) {
              $errors =[];
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

            $uploadedFiles = $request->files->get('pictures');

            if (!empty($uploadedFiles)) {

                if (filesize($uploadedFiles) > 5000000) {
                    return $this->json(['success'=>false, 'message'=> 'The weight of image is to big'], Response::HTTP_EXPECTATION_FAILED);
                }

                if (!is_array($uploadedFiles)) {
                    $fileName = uniqid('contact_') . '.jpg';
                    $fileContent = file_get_contents($uploadedFiles->getPathname());
                    $this->contactStorage->write($fileName, $fileContent);
                    $arrayLinkPictures[] = $fileName;
                    $this->logger->debug('Saved single file: ' . $fileName);
                } else {
                    $this->logger->warning('Unexpected file format: ' . gettype($uploadedFiles));
                }

                if (!empty($arrayLinkPictures)) {
                    $contact->setPictures($arrayLinkPictures);
                }
            }

            $this->entityManager->persist($contact);
            $this->entityManager->flush();

            return $this->json(
                ['success' => true, 'contactId' => $contact->getId()],
                Response::HTTP_CREATED
            );

        } catch (\Exception $e) {
                $this->logger->error("Erreur lors de la création du contact: " . $e->getMessage());
                return $this->json(['error' => 'Erreur serveur'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
    }

    #[Route(methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function get(Request $request): JsonResponse
    {
        try {
            $oneContactByUuid = $request->query->get('uuid');
            $all = $request->query->get('all');

            if (!empty($oneContactByUuid) && empty($all)) {
                $contact = $this->contactRepository->findOneBy(['uuid' => $oneContactByUuid]);

                if (empty($contact)) {
                    return $this->json(
                        ['success' => false, 'message' => 'Contact non trouvé'],
                        Response::HTTP_NOT_FOUND
                    );
                }

                return $this->json($this->normalizeContact($contact), Response::HTTP_OK);
            }

            if (!empty($all) && empty($oneContactByUuid)) {
                $contacts = $this->contactRepository->findAll();
                return $this->json($this->normalizeContacts($contacts), Response::HTTP_OK);
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

            $this->entityManager->remove($contact);
            $this->entityManager->flush();

            return $this->json(
                ['success' => true, 'message' => 'Contact supprimé'],
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return $this->json(
                ['success' => false, 'message' => 'Erreur serveur'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Vérifie si une chaîne base64 contient une image JPEG valide
     *
     * @param string $base64Image L'image encodée en base64
     * @return bool True si l'image est un JPEG valide, False sinon
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

    private function normalizeContact(Contact $contact): array
    {
        return [
            'uuid' => $contact->getUuid(),
            'firstname' => $contact->getFirstname(),
            'lastname' => $contact->getLastname(),
            'companyName' => $contact->getCompanyName(),
            'email' => $contact->getEmail(),
            'title' => $contact->getTitle(),
            'description' => $contact->getDescription(),
            'tags' => $contact->getTags(),
        ];
    }

    private function normalizeContacts(array $contacts): array
    {
        $contactArray = [];
        foreach ($contacts as $contact) {
            $contactArray[] = $this->normalizeContact($contact);
        }
        return $contactArray;
    }
}
