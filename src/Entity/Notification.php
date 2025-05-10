<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $uuid = null;

    #[ORM\Column(length: 200)]
    private ?string $description = null;

    #[ORM\Column(length: 200)]
    private ?string $title = null;

    #[ORM\Column]
    private ?bool $isPriotity = null;

    #[ORM\Column]
    private ?bool $isReading = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readingAt = null;

    #[ORM\ManyToOne(inversedBy: 'notifications')]
    private ?User $user = null;

    #[ORM\PrePersist]
    public function init(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->uuid = Uuid::v4();
        $this->isReading = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function isPriotity(): ?bool
    {
        return $this->isPriotity;
    }

    public function setIsPriotity(bool $isPriotity): static
    {
        $this->isPriotity = $isPriotity;

        return $this;
    }

    public function isReading(): ?bool
    {
        return $this->isReading;
    }

    public function setIsReading(bool $isReading): static
    {
        $this->isReading = $isReading;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getReadingAt(): ?\DateTimeImmutable
    {
        return $this->readingAt;
    }

    public function setReadingAt(?\DateTimeImmutable $readingAt): static
    {
        $this->readingAt = $readingAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
