<?php

namespace App\Entity;

use App\Repository\UserTransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserTransactionRepository::class)]
#[ORM\Table(name: 'user_transaction')]
class UserTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $internalId = null;

    #[ORM\Column(length: 50)]
    private ?string $operationType = null;

    #[ORM\Column(length: 10)]
    private ?string $asset = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 8)]
    private ?string $amount = null;

    #[ORM\Column(length: 255)]
    private ?string $fromAddress = null;

    #[ORM\Column(length: 255)]
    private ?string $toAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $txHash = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $confirmations = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $txLink = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $blockNumber = null;

    // --- NEW: ManyToOne relationship to SfcUser ---
    #[ORM\ManyToOne(targetEntity: SfcUser::class)]
    #[ORM\JoinColumn(nullable: true)] // Set to false if every UserTransaction must have an SfcUser
    private ?SfcUser $sfcUser = null;
    // --- END NEW ---

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // --- Getters and Setters ---
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInternalId(): ?string
    {
        return $this->internalId;
    }

    public function setInternalId(string $internalId): static
    {
        $this->internalId = $internalId;
        return $this;
    }

    public function getOperationType(): ?string
    {
        return $this->operationType;
    }

    public function setOperationType(string $operationType): static
    {
        $this->operationType = $operationType;
        return $this;
    }

    public function getAsset(): ?string
    {
        return $this->asset;
    }

    public function setAsset(string $asset): static
    {
        $this->asset = $asset;
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

    public function getFromAddress(): ?string
    {
        return $this->fromAddress;
    }

    public function setFromAddress(string $fromAddress): static
    {
        $this->fromAddress = $fromAddress;
        return $this;
    }

    public function getToAddress(): ?string
    {
        return $this->toAddress;
    }

    public function setToAddress(string $toAddress): static
    {
        $this->toAddress = $toAddress;
        return $this;
    }

    public function getTxHash(): ?string
    {
        return $this->txHash;
    }

    public function setTxHash(?string $txHash): static
    {
        $this->txHash = $txHash;
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

    public function getConfirmations(): ?int
    {
        return $this->confirmations;
    }

    public function setConfirmations(?int $confirmations): static
    {
        $this->confirmations = $confirmations;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getTxLink(): ?string
    {
        return $this->txLink;
    }

    public function setTxLink(?string $txLink): static
    {
        $this->txLink = $txLink;
        return $this;
    }

    public function getBlockNumber(): ?int
    {
        return $this->blockNumber;
    }

    public function setBlockNumber(?int $blockNumber): static
    {
        $this->blockNumber = $blockNumber;
        return $this;
    }

    // --- NEW: Getter and Setter for SfcUser relationship ---
    public function getSfcUser(): ?SfcUser
    {
        return $this->sfcUser;
    }

    public function setSfcUser(?SfcUser $sfcUser): static
    {
        $this->sfcUser = $sfcUser;
        return $this;
    }
    // --- END NEW ---
}