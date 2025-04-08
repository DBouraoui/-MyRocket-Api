<?php

namespace App\traits;

use App\Entity\ProjectsToDisplay;
use Doctrine\ORM\Mapping\Id;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Uuid;

trait ProjectToDisplay
{
    #[Id]
    public ?int $id;
    #[Uuid]
    public ?string $uuid;
    #[Length(min:1,max: 255)]
    public ?string $title;
    #[Length(min:1,max: 255)]
    public ?string $description;
    #[Length(min:1,max: 255)]
    public ?string $slug;
    #[DateTime]
    public ?\DateTimeImmutable $createdAt;
    #[DateTime]
    public ?\DateTimeImmutable $updatedAt;
    public array $tags;
    public array $link;

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

    public static function fromArray(array $data): self
    {
        if(empty($data)) {
            throw new \Exception('The array is empty');
        }

        $self = new self();

        foreach ($data as $key => $value) {
            if (property_exists($self, $key)) {
                $self->{$key} = $value;
            }
        }
        return $self;
    }

}