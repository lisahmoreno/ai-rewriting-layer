<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'arrangement_portal_content')]
#[ORM\UniqueConstraint(name: 'idx_arrangement_portal', columns: ['arrangement_id', 'portal'])]
#[ORM\Index(name: 'idx_portal_status', columns: ['portal', 'status'])]
#[ORM\HasLifecycleCallbacks]
class ArrangementPortalContent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'integer')]
    private int $arrangementId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $portal; // e.g. 'ku'

    #[ORM\Column(type: 'string', length: 255, options: ['default' => ''])]
    private string $title = '';

    #[ORM\Column(type: 'text', options: ['default' => ''])]
    private string $description = '';

    #[ORM\Column(type: 'string', length: 64, options: ['default' => ''])]
    private string $sourceHash = ''; // SHA-256 of source fields

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'v1.0'])]
    private string $promptVersion = 'v1.0';

    #[ORM\Column(type: 'integer', enumType: EnumRewritingStatus::class)]
    private EnumRewritingStatus $status = EnumRewritingStatus::Pending;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    // Getters/setters trimmed — standard Doctrine accessors.

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

enum EnumRewritingStatus: int
{
    case Pending   = 0;
    case Generated = 1;
    case Failed    = 2;
    case Approved  = 3;
    case Rejected  = 4;
}
