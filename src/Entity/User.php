<?php

namespace App\Entity;

use App\Entity\Chat;
use App\Entity\Message;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\ManyToMany(targetEntity: Chat::class, inversedBy: 'users')]
    private Collection $chats;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'userMessage', orphanRemoval: true)]
    private Collection $messages;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $username = null;

    public function __construct()
    {
        $this->chats = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->username ?? $this->email;
    }

    // public function countUnreadMessages(): int
    // {
    //     // Initialiser le compteur de messages non lus
    //     $unreadCount = 0;
        
    //     // Parcourir tous les chats auxquels l'utilisateur participe
    //     foreach ($this->getChats() as $chat) {
    //         // Créer un critère pour récupérer uniquement les messages non lus de ce chat
    //         $criteria = Criteria::create()
    //             ->andWhere(Criteria::expr()->eq('isRead', false));
            
    //         // Appliquer le critère pour obtenir la collection de messages non lus dans ce chat
    //         $unreadMessages = $chat->getMessages()->matching($criteria);
            
    //         // Parcourir les messages non lus dans ce chat
    //         foreach ($unreadMessages as $message) {
    //             // Vérifier si le message n'est pas envoyé par l'utilisateur lui-même
    //             if ($message->getUserMessage() !== $this) {
    //                 // Si le message n'est pas envoyé par l'utilisateur lui-même, incrémenter le compteur global
    //                 $unreadCount++;
    //             }
    //         }
    //     }
        
    //     return $unreadCount;
    // }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection<int, Chat>
     */
    public function getChats(): Collection
    {
        return $this->chats;
    }

    public function addChat(Chat $chat): static
    {
        if (!$this->chats->contains($chat)) {
            $this->chats->add($chat);
        }

        return $this;
    }

    public function removeChat(Chat $chat): static
    {
        $this->chats->removeElement($chat);

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setUserMessage($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getUserMessage() === $this) {
                $message->setUserMessage(null);
            }
        }

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }
}
