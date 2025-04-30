<?php

namespace App\Controller;

use App\DTO\contact\ContactCreateDTO;
use App\Entity\Contact;
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
use Symfony\Contracts\Cache\CacheInterface;

#[Route('/api/user/contact', name: 'app_contact')]
#[IsGranted('PUBLIC_ACCESS')]
final class ContactController extends AbstractController
{
    public const GET_ALLCONTACTS = 'getAllContacts';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface        $logger,
        private readonly FilesystemOperator     $contactStorage,
        private readonly ValidatorInterface     $validator, private readonly CacheInterface $cache,
    )
    {}

    #[Route(methods: ['POST'])]
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
}
