<?php

namespace App\Entity;

use App\Repository\ChatMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatMessageRepository::class)]
#[ORM\Table(name: 'chat_message')]
class ChatMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AnnonceSortie::class, inversedBy: 'chatMessages')]
    #[ORM\JoinColumn(name: 'annonce_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?AnnonceSortie $annonceSortie = null;

    #[ORM\ManyToOne(targetEntity: ChatGroupe::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'chat_groupe_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ChatGroupe $chatGroupe = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'chatMessages')]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(type: 'text')]
    private ?string $content = null;

    #[ORM\Column(name: 'message_type', type: 'string', length: 50)]
    private ?string $messageType = null;

    #[ORM\ManyToOne(targetEntity: Poll::class, inversedBy: 'chatMessages')]
    #[ORM\JoinColumn(name: 'poll_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Poll $poll = null;

    #[ORM\Column(name: 'meta_json', type: 'text', nullable: true)]
    private ?string $metaJson = null;

    #[ORM\Column(name: 'sent_at', type: 'datetime')]
    private ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(name: 'edited_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $editedAt = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\Column(name: 'attachment_path', type: 'string', length: 255, nullable: true)]
    private ?string $attachmentPath = null;

    #[ORM\Column(name: 'attachment_type', type: 'string', length: 60, nullable: true)]
    private ?string $attachmentType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getAnnonceSortie(): ?AnnonceSortie
    {
        return $this->annonceSortie;
    }

    public function setAnnonceSortie(?AnnonceSortie $annonceSortie): self
    {
        $this->annonceSortie = $annonceSortie;

        return $this;
    }

    public function getChatGroupe(): ?ChatGroupe
    {
        return $this->chatGroupe;
    }

    public function setChatGroupe(?ChatGroupe $chatGroupe): self
    {
        $this->chatGroupe = $chatGroupe;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getSender(): ?User
    {
        return $this->user;
    }

    public function setSender(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getMessageType(): ?string
    {
        return $this->messageType;
    }

    public function setMessageType(string $messageType): self
    {
        $this->messageType = $messageType;

        return $this;
    }

    public function getPoll(): ?Poll
    {
        return $this->poll;
    }

    public function setPoll(?Poll $poll): self
    {
        $this->poll = $poll;

        return $this;
    }

    public function getMetaJson(): ?string
    {
        return $this->metaJson;
    }

    public function setMetaJson(?string $metaJson): self
    {
        $this->metaJson = $metaJson;

        return $this;
    }

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(\DateTimeInterface $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getEditedAt(): ?\DateTimeInterface
    {
        return $this->editedAt;
    }

    public function setEditedAt(?\DateTimeInterface $editedAt): self
    {
        $this->editedAt = $editedAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getAttachmentPath(): ?string
    {
        return $this->attachmentPath;
    }

    public function setAttachmentPath(?string $attachmentPath): self
    {
        $this->attachmentPath = $attachmentPath;

        return $this;
    }

    public function getAttachmentType(): ?string
    {
        return $this->attachmentType;
    }

    public function setAttachmentType(?string $attachmentType): self
    {
        $this->attachmentType = $attachmentType;

        return $this;
    }
}
