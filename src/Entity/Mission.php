<?php

namespace App\Entity;

use App\Repository\MissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MissionRepository::class)]
class Mission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $client = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: "Service date is required")]
    private ?\DateTimeInterface $serviceDate = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Product name is required")]
    #[Assert\Length(max: 255)]
    private ?string $productName = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotNull(message: "Quantity is required")]
    #[Assert\Positive(message: "Quantity must be a positive number")]
    private ?int $quantity = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Destination country is required")]
    #[Assert\Length(max: 255)]
    private ?string $destinationCountry = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Vendor name is required")]
    #[Assert\Length(max: 255)]
    private ?string $vendorName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Vendor email is required")]
    #[Assert\Email(message: "The email '{{ value }}' is not a valid email.")]
    #[Assert\Length(max: 255)]
    private ?string $vendorEmail = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?User
    {
        return $this->client;
    }

    public function setClient(?User $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getServiceDate(): ?\DateTimeInterface
    {
        return $this->serviceDate;
    }

    public function setServiceDate(\DateTimeInterface $serviceDate): static
    {
        $this->serviceDate = $serviceDate;

        return $this;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getDestinationCountry(): ?string
    {
        return $this->destinationCountry;
    }

    public function setDestinationCountry(string $destinationCountry): static
    {
        $this->destinationCountry = $destinationCountry;

        return $this;
    }

    public function getVendorName(): ?string
    {
        return $this->vendorName;
    }

    public function setVendorName(string $vendorName): static
    {
        $this->vendorName = $vendorName;

        return $this;
    }

    public function getVendorEmail(): ?string
    {
        return $this->vendorEmail;
    }

    public function setVendorEmail(string $vendorEmail): static
    {
        $this->vendorEmail = $vendorEmail;

        return $this;
    }
}