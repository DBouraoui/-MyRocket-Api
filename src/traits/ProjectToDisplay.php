<?php

namespace App\traits;

use App\Entity\ProjectsToDisplay;

trait ProjectToDisplay
{
    public ?int $id = null;
    public ?string $uuid = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $slug = null;
    public ?\DateTimeImmutable $createdAt = null;
    public ?\DateTimeImmutable $updatedAt = null;
    public array $tags = [];
    public array $link = [];

    public function normalize(ProjectsToDisplay $projectToDisplay): array
    {
        return [
            'uuid' => $projectToDisplay->getUuid(),
            'title' => $projectToDisplay->getTitle(),
            'description' => $projectToDisplay->getDescription(),
            'slug' => $projectToDisplay->getSlug(),
            'createdAt' => $projectToDisplay->getCreatedAt(),
            'updatedAt' => $projectToDisplay->getUpdatedAt(),
            'tags' => $projectToDisplay->getTags(),
            'link' => $projectToDisplay->getLink(),
        ];
    }

    public function normalizeArray(array $array): array
    {
        $projectsToDiplayArray = [];
        foreach ($array as $projectToDisplay) {
            $projectsToDiplayArray[] = $this->normalize($projectToDisplay);
        }

        return $projectsToDiplayArray;
    }

}