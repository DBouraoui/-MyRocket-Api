<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\ProjectsToDisplay;
use App\Repository\ProjectsToDisplayRepository;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/user/project', name: 'app_projects_to_diplay_')]
#[IsGranted('PUBLIC_ACCESS')]
final class ProjectsToDiplayController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ProjectsToDisplayRepository $projectsToDisplayRepository,
        private readonly FilesystemOperator $projectStorage
    ) {
    }

    #[Route(name: 'app_projects_to_diplay_get', methods: ['GET'])]
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
    public function getImage(string $filename): Response
    {
        try {
            if (!$this->projectStorage->fileExists($filename)) {
                return new Response('Image non trouvée', Response::HTTP_NOT_FOUND);
            }

            // Récupérer le contenu du fichier
            $fileContent = $this->projectStorage->read($filename);

            // Déterminer le type MIME
            $finfo = new \finfo(\FILEINFO_MIME_TYPE);
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

    private function normalizeProject(ProjectsToDisplay $projectsToDisplay, bool $withImageUrls = false): array
    {
        $normalizedProject = [
            'uuid' => $projectsToDisplay->getUuid(),
            'title' => $projectsToDisplay->getTitle(),
            'description' => $projectsToDisplay->getDescription(),
            'link' => $projectsToDisplay->getLink(),
            'tags' => $projectsToDisplay->getTags(),
            'createdAt' => $projectsToDisplay->getCreatedAt()->format('d-m-Y'),
            'updatedAt' => $projectsToDisplay->getUpdatedAt()->format('d-m-Y'),
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
                        'url' => $imageUrl,
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
