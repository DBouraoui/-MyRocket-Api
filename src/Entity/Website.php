<?php

namespace App\Entity;

use App\Repository\WebsiteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: WebsiteRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Website
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private ?string $uuid = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $url = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\ManyToOne(inversedBy: 'websites')]
    private ?User $user = null;

    #[ORM\OneToOne(mappedBy: 'website', cascade: ['persist', 'remove'])]
    private ?WebsiteMutualised $websiteMutualised = null;

    #[ORM\OneToOne(mappedBy: 'website', cascade: ['persist', 'remove'])]
    private ?WebsiteVps $websiteVps = null;

    #[ORM\OneToOne(mappedBy: 'website', cascade: ['persist', 'remove'])]
    private ?WebsiteContract $WebsiteContract = null;

    #[ORM\OneToOne(mappedBy: 'website', cascade: ['persist', 'remove'])]
    private ?MaintenanceContract $maintenanceContract = null;

    #[ORM\PrePersist]
    public function init() {
        $this->createdAt = new \DateTimeImmutable('now');
        $this->updatedAt = new \DateTimeImmutable('now');
        $this->uuid = Uuid::v4();
    }
    #[ORM\PreUpdate]
    public function update() {
        $this->updatedAt = new \DateTimeImmutable('now');
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getWebsiteMutualised(): ?WebsiteMutualised
    {
        return $this->websiteMutualised;
    }

    public function setWebsiteMutualised(WebsiteMutualised $websiteMutualised): static
    {
        // set the owning side of the relation if necessary
        if ($websiteMutualised->getWebsite() !== $this) {
            $websiteMutualised->setWebsite($this);
        }

        $this->websiteMutualised = $websiteMutualised;

        return $this;
    }

    public function getWebsiteVps(): ?WebsiteVps
    {
        return $this->websiteVps;
    }

    public function setWebsiteVps(?WebsiteVps $websiteVps): static
    {
        // unset the owning side of the relation if necessary
        if ($websiteVps === null && $this->websiteVps !== null) {
            $this->websiteVps->setWebsite(null);
        }

        // set the owning side of the relation if necessary
        if ($websiteVps !== null && $websiteVps->getWebsite() !== $this) {
            $websiteVps->setWebsite($this);
        }

        $this->websiteVps = $websiteVps;

        return $this;
    }

    public function getWebsiteContract(): ?WebsiteContract
    {
        return $this->WebsiteContract;
    }

    public function setWebsiteContract(?WebsiteContract $WebsiteContract): static
    {
        // unset the owning side of the relation if necessary
        if ($WebsiteContract === null && $this->WebsiteContract !== null) {
            $this->WebsiteContract->setWebsite(null);
        }

        // set the owning side of the relation if necessary
        if ($WebsiteContract !== null && $WebsiteContract->getWebsite() !== $this) {
            $WebsiteContract->setWebsite($this);
        }

        $this->WebsiteContract = $WebsiteContract;

        return $this;
    }

    public function getMaintenanceContract(): ?MaintenanceContract
    {
        return $this->maintenanceContract;
    }

    public function setMaintenanceContract(?MaintenanceContract $maintenanceContract): static
    {
        // unset the owning side of the relation if necessary
        if ($maintenanceContract === null && $this->maintenanceContract !== null) {
            $this->maintenanceContract->setWebsite(null);
        }

        // set the owning side of the relation if necessary
        if ($maintenanceContract !== null && $maintenanceContract->getWebsite() !== $this) {
            $maintenanceContract->setWebsite($this);
        }

        $this->maintenanceContract = $maintenanceContract;

        return $this;
    }
}
