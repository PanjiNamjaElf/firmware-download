<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SoftwareVersionRepository;
use App\Validator\UniqueLatestInProductName;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A single firmware version entry for a specific hardware product line.
 *
 * isLci and lciHwType are stored explicitly (not derived from the name string)
 * to prevent silent matching failures from admin typos.
 *
 * systemVersionAlt is auto-derived from systemVersion on every save via
 * setSystemVersion(), so the matching key is always in sync with the display version.
 */
#[ORM\Entity(repositoryClass: SoftwareVersionRepository::class)]
#[ORM\Table(name: 'software_version')]
#[ORM\Index(columns: ['is_lci'], name: 'idx_sw_is_lci')]
#[ORM\Index(columns: ['sort_order', 'name'], name: 'idx_sw_sort')]
#[ORM\HasLifecycleCallbacks]
#[UniqueLatestInProductName]
class SoftwareVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Product name is required.')]
    #[Assert\Length(max: 100)]
    private string $name = '';

    /** Full version string with "v" prefix — for display. Example: v3.3.7.mmipri.c */
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'System version is required.')]
    #[Assert\Length(max: 100)]
    #[Assert\Regex(
        pattern: '/^v/i',
        message: 'System version must start with "v" (e.g. v3.3.7.mmipri.c).',
    )]
    private string $systemVersion = '';

    /**
     * Version string without the "v" prefix — used for case-insensitive matching.
     * Auto-derived from systemVersion; do not set this field manually via the admin form.
     */
    #[ORM\Column(length: 100)]
    private string $systemVersionAlt = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Url(message: 'Please enter a valid URL or leave this empty.')]
    private ?string $link = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Url(message: 'Please enter a valid URL for the ST download link, or leave it empty.')]
    private ?string $stLink = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Url(message: 'Please enter a valid URL for the GD download link, or leave it empty.')]
    private ?string $gdLink = null;

    /** When true, the customer is already on the latest version — no download link is shown. */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isLatest = false;

    /** When true, this entry belongs to the LCI (Life Cycle Impulse) product family. */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isLci = false;

    /** For LCI entries: 'CIC', 'NBT', or 'EVO'. Must be null for standard entries. */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $lciHwType = null;

    /** Controls the matching iteration order — mirrors the original JSON array index. */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\GreaterThanOrEqual(0)]
    private int $sortOrder = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Enforces LCI/HW type consistency:
     * - isLci = true  → lciHwType must be CIC, NBT, or EVO.
     * - isLci = false → lciHwType must be null.
     */
    #[Assert\Callback]
    public function validateLciHwTypeConsistency(ExecutionContextInterface $context): void
    {
        if ($this->isLci) {
            if (empty($this->lciHwType)) {
                $context->buildViolation(
                    'You marked this as an LCI device, so you must also select an LCI Hardware System (CIC, NBT, or EVO).',
                )->atPath('lciHwType')->addViolation();
            } elseif (! in_array(strtoupper($this->lciHwType), ['CIC', 'NBT', 'EVO'], true)) {
                $context->buildViolation('Invalid LCI hardware type "{{ value }}". Must be CIC, NBT, or EVO.')
                    ->setParameter('{{ value }}', $this->lciHwType)
                    ->atPath('lciHwType')
                    ->addViolation();
            }
        } elseif (! empty($this->lciHwType)) {
            $context->buildViolation('LCI Hardware System must be left empty for standard (non-LCI) devices.')
                ->atPath('lciHwType')
                ->addViolation();
        }
    }

    /**
     * Warns when "Is Latest" is checked but ST/GD download links are still filled.
     * Latest entries produce no download link for the customer.
     */
    #[Assert\Callback]
    public function validateLatestHasNoLinks(ExecutionContextInterface $context): void
    {
        if ($this->isLatest && (! empty($this->stLink) || ! empty($this->gdLink))) {
            $context->buildViolation(
                'This entry is marked as "Latest". Latest entries should not have ST or GD download links — '
                .'customers already on the latest version do not receive a download link. '
                .'Clear the ST and GD link fields, or uncheck "Is Latest".',
            )->atPath('isLatest')->addViolation();
        }
    }

    // -------------------------------------------------------------------------
    // Getters and setters
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSystemVersion(): string
    {
        return $this->systemVersion;
    }

    public function setSystemVersion(string $systemVersion): static
    {
        $this->systemVersion = $systemVersion;
        // Auto-derive the matching key so both fields always stay in sync.
        $this->systemVersionAlt = ltrim($systemVersion, 'vV');

        return $this;
    }

    public function getSystemVersionAlt(): string
    {
        return $this->systemVersionAlt;
    }

    /**
     * Doctrine requires this setter for entity hydration from the database.
     * In normal usage systemVersionAlt is auto-derived by setSystemVersion().
     */
    public function setSystemVersionAlt(string $systemVersionAlt): static
    {
        $this->systemVersionAlt = $systemVersionAlt;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = ($link !== null && $link !== '') ? $link : null;

        return $this;
    }

    public function getStLink(): ?string
    {
        return $this->stLink;
    }

    public function setStLink(?string $stLink): static
    {
        $this->stLink = ($stLink !== null && $stLink !== '') ? $stLink : null;

        return $this;
    }

    public function getGdLink(): ?string
    {
        return $this->gdLink;
    }

    public function setGdLink(?string $gdLink): static
    {
        $this->gdLink = ($gdLink !== null && $gdLink !== '') ? $gdLink : null;

        return $this;
    }

    public function isLatest(): bool
    {
        return $this->isLatest;
    }

    public function setIsLatest(bool $isLatest): static
    {
        $this->isLatest = $isLatest;

        return $this;
    }

    public function isLci(): bool
    {
        return $this->isLci;
    }

    public function setIsLci(bool $isLci): static
    {
        $this->isLci = $isLci;

        return $this;
    }

    public function getLciHwType(): ?string
    {
        return $this->lciHwType;
    }

    public function setLciHwType(?string $lciHwType): static
    {
        $this->lciHwType = ($lciHwType !== null && $lciHwType !== '') ? $lciHwType : null;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function __toString(): string
    {
        return sprintf('%s — %s', $this->name, $this->systemVersion);
    }
}
