<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Administrateur;

use App\DTO\ProjectToDisplay\ProjectCreateDTO;
use App\DTO\ProjectToDisplay\ProjectUpdateDTO;
use App\Entity\ProjectsToDisplay;
use App\Repository\ProjectsToDisplayRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(path: '/api/administrateur/projects-display', name: 'api_administrateur_projects-display')]
#[IsGranted('ROLE_ADMIN')]
class AdministrateurProjectDisplay extends AbstractController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly SluggerInterface $slugger,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly FilesystemOperator $projectStorage,
        private readonly ProjectsToDisplayRepository $projectsToDisplayRepository
    ) {
    }

    #[Route(name: '_post', methods: ['POST'])]
    public function index(Request $request): JsonResponse
    {
        try {
            // Récupérer les données du formulaire
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $link = json_decode($request->request->get('link', '[]'), true);
            $tags = json_decode($request->request->get('tags', '[]'), true);

            // Créer un tableau de données pour la validation
            $data = [
                'title' => $title,
                'description' => $description,
                'link' => $link,
                'tags' => $tags,
            ];

            // Validation du DTO
            $projectCreateDTO = ProjectCreateDTO::fromArray($data);
            $violations = $this->validator->validate($projectCreateDTO);

            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[$violation->getPropertyPath()] = $violation->getMessage();
                }

                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            // Créer une nouvelle entité projet
            $project = new ProjectsToDisplay();
            $project->setTitle($projectCreateDTO->title);
            $project->setDescription($projectCreateDTO->description);
            $project->setSlug($this->slugger->slug($projectCreateDTO->title));
            $project->setLink($projectCreateDTO->link);
            $project->setTags($projectCreateDTO->tags);

            // Traitement des images
            $arrayLinkPictures = [];

            // Vérifier si des fichiers ont été uploadés
            $uploadedFiles = $request->files->get('pictures');

            if ($uploadedFiles) {
                // Si c'est un tableau d'images
                if (is_array($uploadedFiles)) {
                    foreach ($uploadedFiles as $uploadedFile) {
                        $fileName = $this->processUploadedFile($uploadedFile);
                        if ($fileName) {
                            $arrayLinkPictures[] = $fileName;
                        }
                    }
                }
                // Si c'est une seule image
                else {
                    $fileName = $this->processUploadedFile($uploadedFiles);
                    if ($fileName) {
                        $arrayLinkPictures[] = $fileName;
                    }
                }
            }

            // Mettre à jour les images du projet
            $project->setPictures($arrayLinkPictures);

            // Persister le projet
            $this->entityManager->persist($project);
            $this->entityManager->flush();

            return $this->json(
                ['success' => true, 'message' => 'Le projet a été créé'],
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route(name: '_put', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $uprojectUpdateDTO = ProjectUpdateDTO::fromArray($data);

            $violations = $this->validator->validate($uprojectUpdateDTO);

            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[$violation->getPropertyPath()] = $violation->getMessage();
                }

                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $projects = $this->projectsToDisplayRepository->findOneBy(['uuid' => $uprojectUpdateDTO->uuid]);

            if (empty($projects)) {
                return $this->json(['success' => false], Response::HTTP_EXPECTATION_FAILED);
            }
            unset($data['uuid']);

            $allowField = ['title', 'description', 'link', 'tags'];

            foreach ($data as $field => $value) {
                if (!in_array($field, $allowField)) {
                    return $this->json(['error' => 'Field not allowed: ' . $field], Response::HTTP_BAD_REQUEST);
                }
            }

            foreach ($data as $field => $value) {
                $setter = 'set' . ucfirst($field);
                if (method_exists($projects, $setter)) {
                    $projects->{$setter}($value);
                }
            }

            $this->entityManager->flush();

            return $this->json(['success' => true], Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route(name: 'app_projects_to_diplay_delete', methods: ['DELETE'])]
    public function delete(Request $request): JsonResponse
    {
        try {
            $key = $request->query->get('key');
            $project = $this->projectsToDisplayRepository->findOneByUuidOrSlug($key);

            if (empty($project)) {
                return $this->json(
                    ['success' => false, 'message' => 'Aucun projet ne correspond'],
                    Response::HTTP_NOT_FOUND
                );
            }

            $this->entityManager->remove($project);
            $this->entityManager->flush();

            return $this->json(
                ['success' => true, 'message' => 'Projet supprimé'],
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function processUploadedFile($uploadedFile): ?string
    {
        if (!$uploadedFile) {
            return null;
        }

        try {
            // Vérifier la taille du fichier
            if ($uploadedFile->getSize() > 5000000) {
                $this->logger->warning('Fichier trop volumineux: ' . $uploadedFile->getClientOriginalName());

                return null;
            }

            // Générer un nom de fichier unique
            $extension = $uploadedFile->getClientOriginalExtension();
            $fileName = uniqid('project_') . '.' . ($extension ?: 'jpg');

            // Lire le contenu du fichier
            $fileContent = file_get_contents($uploadedFile->getPathname());

            // Stocker le fichier
            $this->projectStorage->write($fileName, $fileContent);
            $this->logger->debug('Fichier sauvegardé: ' . $fileName);

            return $fileName;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du traitement du fichier: ' . $e->getMessage());

            return null;
        }
    }
}
