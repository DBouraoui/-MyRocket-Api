<?php

namespace App\Entity;

use App\Repository\WebsiteContractRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebsiteContractRepository::class)]
class WebsiteContract
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private ?string $uuid = null;

    #[ORM\Column(length: 10)]
    private ?string $annualCost = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tva = null;

    #[ORM\Column(length: 20)]
    private ?string $reccurence = null;

    #[ORM\Column(length: 100)]
    private ?string $prestation = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $firstPaymentAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $lastPaymentAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $nextPaymentAt = null;

    #[ORM\ManyToOne(inversedBy: 'WebsiteContract')]
    private ?User $user = null;

    #[ORM\OneToOne(inversedBy: 'WebsiteContract', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Website $website = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getAnnualCost(): ?string
    {
        return $this->annualCost;
    }

    public function setAnnualCost(string $annualCost): static
    {
        $this->annualCost = $annualCost;

        return $this;
    }

    public function getTva(): ?string
    {
        return $this->tva;
    }

    public function setTva(?string $tva): static
    {
        $this->tva = $tva;

        return $this;
    }

    public function getReccurence(): ?string
    {
        return $this->reccurence;
    }

    public function setReccurence(string $reccurence): static
    {
        $this->reccurence = $reccurence;

        return $this;
    }

    public function getPrestation(): ?string
    {
        return $this->prestation;
    }

    public function setPrestation(string $prestation): static
    {
        $this->prestation = $prestation;

        return $this;
    }

    public function getFirstPaymentAt(): ?\DateTimeImmutable
    {
        return $this->firstPaymentAt;
    }

    public function setFirstPaymentAt(\DateTimeImmutable $firstPaymentAt): static
    {
        $this->firstPaymentAt = $firstPaymentAt;

        return $this;
    }

    public function getLastPaymentAt(): ?\DateTimeImmutable
    {
        return $this->lastPaymentAt;
    }

    public function setLastPaymentAt(\DateTimeImmutable $lastPaymentAt): static
    {
        $this->lastPaymentAt = $lastPaymentAt;

        return $this;
    }

    public function getNextPaymentAt(): ?\DateTimeImmutable
    {
        return $this->nextPaymentAt;
    }

    public function setNextPaymentAt(\DateTimeImmutable $nextPaymentAt): static
    {
        $this->nextPaymentAt = $nextPaymentAt;

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

    public function getWebsite(): ?Website
    {
        return $this->website;
    }

    public function setWebsite(?Website $website): static
    {
        $this->website = $website;

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
}
