<?php

namespace App\Controller;

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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/project', name: 'app_projects_to_diplay_')]
final class ProjectsToDiplayController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface      $entityManager,
        private readonly LoggerInterface             $logger,
        private readonly SluggerInterface            $slugger,
        private readonly ProjectsToDisplayRepository $projectsToDisplayRepository,
        private readonly ValidatorInterface $validator,
        private readonly FilesystemOperator $projectStorage
    ) {}

    #[Route(name: 'app_projects_to_diplay', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
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
                'tags' => $tags
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

    #[Route(name: 'app_projects_to_diplay_get', methods: ['GET'])]
    #[isGranted('PUBLIC_ACCESS')]
    public function get(Request $request): JsonResponse
    {
        try {
            $getByKey = $request->query->get('key');
            $all = $request->query->get('all');

            if (!empty($getByKey) && empty($all)) {
                $project = $this->projectsToDisplayRepository->findOneByUuidOrSlug($getByKey);

                if (empty($project)) {
                    return $this->json(
                        ['success' => false, 'message' => 'Projet non trouvé'],
                        Response::HTTP_NOT_FOUND
                    );
                }

                return $this->json($this->normalizeProject($project, true), Response::HTTP_OK);
            }

            if (!empty($all) && empty($getByKey)) {
                $projects = $this->projectsToDisplayRepository->findAll();
                return $this->json($this->normalizeProjects($projects, true), Response::HTTP_OK);
            }

            return $this->json(
                ['success' => false, 'message' => 'Aucun critère ne correspond'],
                Response::HTTP_NOT_FOUND
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/projects/image/{filename}', name: 'app_projects_image_get', methods: ['GET'])]
    #[isGranted('PUBLIC_ACCESS')]
    public function getImage(string $filename): Response
    {
        try {
            if (!$this->projectStorage->fileExists($filename)) {
                return new Response('Image non trouvée', Response::HTTP_NOT_FOUND);
            }

            // Récupérer le contenu du fichier
            $fileContent = $this->projectStorage->read($filename);

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
            $this->logger->error('Erreur lors de la récupération de l\'image: ' . $e->getMessage());
            return new Response('Erreur lors de la récupération de l\'image', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(name: 'app_projects_to_diplay_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
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
                return $this->json(['success'=>false],Response::HTTP_EXPECTATION_FAILED);
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
                    $projects->$setter($value);
                }
            }

            $this->entityManager->flush();

            return $this->json(['success'=>true], Response::HTTP_OK);
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route(name: 'app_projects_to_diplay_delete', methods: ['DELETE'])]
    #[isGranted('ROLE_ADMIN')]
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

    private function normalizeProject(ProjectsToDisplay $projectsToDisplay, bool $withImageUrls = false): array
    {
        $normalizedProject = [
            'uuid' => $projectsToDisplay->getUuid(),
            'title' => $projectsToDisplay->getTitle(),
            'description' => $projectsToDisplay->getDescription(),
            'link' => $projectsToDisplay->getLink(),
            'tags' => $projectsToDisplay->getTags(),
            'createdAt' => $projectsToDisplay->getCreatedAt()->format('d-m-Y H:i:s'),
            'updatedAt' => $projectsToDisplay->getUpdatedAt()->format('d-m-Y H:i:s'),
        ];

        // Récupérer les noms de fichiers d'images
        $pictureFilenames = $projectsToDisplay->getPictures() ?? [];

        if ($withImageUrls && !empty($pictureFilenames)) {
            $pictureUrls = [];

            foreach ($pictureFilenames as $filename) {
                $imageUrl = $this->getImageUrl($filename);
                if ($imageUrl) {
                    $pictureUrls[] = [
                        'filename' => $filename,
                        'url' => $imageUrl
                    ];
                }
            }

            $normalizedProject['pictures'] = $pictureUrls;
        } else {
            $normalizedProject['pictures'] = $pictureFilenames;
        }

        return $normalizedProject;
    }

    private function normalizeProjects(array $projects, bool $withImageUrls = false): array
    {
        $projectsToDisplay = [];
        foreach ($projects as $project) {
            $projectsToDisplay[] = $this->normalizeProject($project, $withImageUrls);
        }
        return $projectsToDisplay;
    }

    private function getImageUrl(string $filename): ?string
    {
        try {
            if (empty($filename)) {
                return null;
            }

            if (!$this->projectStorage->fileExists($filename)) {
                $this->logger->warning('Le fichier demandé n\'existe pas: ' . $filename);
                return null;
            }

            return $this->generateUrl('app_projects_to_diplay_app_projects_image_get', ['filename' => $filename], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération de l\'URL de l\'image: ' . $e->getMessage());
            return null;
        }
    }
}
