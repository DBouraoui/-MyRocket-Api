<?php

namespace App\Controller;

use App\Entity\ProjectsToDisplay;
use App\Repository\ProjectsToDisplayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/project', name: 'app_projects_to_diplay')]
final class ProjectsToDiplayController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly SluggerInterface $slugger,
        private readonly ProjectsToDisplayRepository $projectsToDisplayRepository,
    ) {}

    #[Route(name: 'app_projects_to_diplay', methods: ['POST'])]
    public function index(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $requiredFields = ['title', 'description', 'link', 'tags'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->json(
                        ['success' => false, 'message' => 'Le champ ' . $field . ' est requis'],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            $project = new ProjectsToDisplay();
            $project->setTitle($data['title']);
            $project->setDescription($data['description']);
            $project->setSlug($this->slugger->slug($data['title']));
            $project->setLink($data['link']);
            $project->setTags($data['tags']);

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

                return $this->json($this->normalizeProject($project), Response::HTTP_OK);
            }

            if (!empty($all) && empty($getByKey)) {
                $projects = $this->projectsToDisplayRepository->findAll();
                return $this->json($this->normalizeProjects($projects), Response::HTTP_OK);
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

    #[Route(name: 'app_projects_to_diplay_update', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Correction ici: findOneBy prend un tableau associatif comme critère
            $projects = $this->projectsToDisplayRepository->findOneBy(['uuid' => $data['uuid']]);

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

    private function normalizeProject(ProjectsToDisplay $projectsToDisplay): array
    {
        return [
            'uuid'=> $projectsToDisplay->getUuid(),
            'title'=> $projectsToDisplay->getTitle(),
            'description'=> $projectsToDisplay->getDescription(),
            'link'=> $projectsToDisplay->getLink(),
            'tags' => $projectsToDisplay->getTags(),
            'createdAt'=> $projectsToDisplay->getCreatedAt()->format('d-m-Y H:i:s'),
            'updatedAt'=> $projectsToDisplay->getUpdatedAt()->format('d-m-Y H:i:s'),
        ];
    }

    private function normalizeProjects(array $projects): array
    {
        $projectsToDisplay = [];
        foreach ($projects as $project) {
            $projectsToDisplay[] = $this->normalizeProject($project);
        }
        return $projectsToDisplay;
    }
}
