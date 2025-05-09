<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private ?string $uuid = null;

    #[ORM\Column(length: 255)]
    private ?string $amount = null;

    #[ORM\Column(length: 255)]
    private ?string $tva = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    private ?WebsiteContract $websiteContract = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?bool $isPaid = null;

    #[ORM\Column]
    private ?bool $isReminderSent = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    #[ORM\PrePersist]
    public function init()
    {
        $this->createdAt = new \DateTimeImmutable('now');
        $this->uuid = Uuid::v4();
        $this->isReminderSent = false;
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

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getTva(): ?string
    {
        return $this->tva;
    }

    public function setTva(string $tva): static
    {
        $this->tva = $tva;

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

    public function getWebsiteContract(): ?WebsiteContract
    {
        return $this->websiteContract;
    }

    public function setWebsiteContract(?WebsiteContract $websiteContract): static
    {
        $this->websiteContract = $websiteContract;

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

    public function isPaid(): ?bool
    {
        return $this->isPaid;
    }

    public function setIsPaid(bool $isPaid): static
    {
        $this->isPaid = $isPaid;

        return $this;
    }

    public function isReminderSent(): ?bool
    {
        return $this->isReminderSent;
    }

    public function setIsReminderSent(bool $isReminderSent): static
    {
        $this->isReminderSent = $isReminderSent;

        return $this;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function setReminderSentAt(?\DateTimeImmutable $reminderSentAt): static
    {
        $this->reminderSentAt = $reminderSentAt;

        return $this;
    }
}
