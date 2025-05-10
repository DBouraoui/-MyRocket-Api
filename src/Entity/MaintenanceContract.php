<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\MaintenanceContractRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MaintenanceContractRepository::class)]
#[ORM\HasLifecycleCallbacks]
class MaintenanceContract
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private ?string $uuid = null;

    #[ORM\Column]
    private ?float $monthlyCost = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'maintenanceContracts')]
    private ?User $user = null;

    #[ORM\OneToOne(inversedBy: 'maintenanceContract', cascade: ['persist'])]
    private ?Website $website = null;

    #[ORM\Column(length: 30)]
    private ?string $reccurence = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $firstPaymentAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $lastPaymentAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $nextPaymentAt = null;

    #[ORM\PrePersist]
    public function init()
    {
        $this->createdAt = new \DateTimeImmutable('now');
        $this->uuid = Uuid::v4();
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

    public function getMonthlyCost(): ?float
    {
        return $this->monthlyCost;
    }

    public function setMonthlyCost(float $monthlyCost): static
    {
        $this->monthlyCost = $monthlyCost;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

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

    public function getReccurence(): ?string
    {
        return $this->reccurence;
    }

    public function setReccurence(string $reccurence): static
    {
        $this->reccurence = $reccurence;

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
}
