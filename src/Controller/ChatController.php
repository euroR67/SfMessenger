<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\User;
use App\Entity\Message;
use App\Form\AddUserType;
use App\Repository\ChatRepository;
use Symfony\Component\Mercure\Update;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ChatController extends AbstractController
{
    // Lister tout les utilisateurs
    #[Route('/chat', name: 'app_chat')]
    public function index(EntityManagerInterface $entityManager, Security $security): Response
    {
        // Récupérer l'utilisateur actuellement connecté
        $currentUser = $security->getUser();

        // Récupérer tous les utilisateurs sauf l'utilisateur actuellement connecté
        $users = $entityManager->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.id != :currentUserId')
            ->setParameter('currentUserId', $currentUser->getId())
            ->getQuery()
            ->getResult();

        return $this->render('chat/index.html.twig', [
            'users' => $users,
        ]);
    }

    // Démarrer un chat avec un utilisateur
    #[Route('/chat/startChat/{id}', name: 'app_start_chat')]
    public function startChat(User $user, EntityManagerInterface $entityManager, Security $security, ChatRepository $chatRepository): Response
    {
        // Récupérer l'utilisateur actuellement connecté
        $currentUser = $security->getUser();

        // Vérifier si un chat existe déjà entre les deux utilisateurs
        $existingChat = $chatRepository->findChatByUsers($currentUser, $user);

        if ($existingChat) {
            // Si un chat existe déjà, rediriger l'utilisateur vers ce chat
            return $this->redirectToRoute('app_chat_view', ['id' => $existingChat->getId()]);
        }

        // Si aucun chat n'existe, créer un nouveau chat
        $chat = new Chat();
        $chat->addUser($currentUser);
        $chat->addUser($user);

        $entityManager->persist($chat);
        $entityManager->flush();

        return $this->redirectToRoute('app_chat_view', ['id' => $chat->getId()]);
    }

    // Envoyer un message dans un chat
    #[Route('/chat/{id}/send', name: 'app_send_message', methods: ['POST'])]
    public function sendMessage(Chat $chat, Request $request, EntityManagerInterface $entityManager, Security $security, PublisherInterface $publisher): Response
    {
        // Créer un nouveau message
        $message = new Message();

        // Définir le contenu du message à partir de la requête
        $message->setContent($request->request->get('content'));

        // Définir l'utilisateur du message comme l'utilisateur actuellement connecté
        $message->setUserMessage($security->getUser());

        // Définir le chat du message
        $message->setChat($chat);

        // Enregistrer le message dans la base de données
        $entityManager->persist($message);
        $entityManager->flush();

        // Publier une mise à jour Mercure
        $update = new Update(
            'http://localhost:8000/chat/'.$chat->getId(),
            json_encode(['message' => $message->getContent(), 'user' => $security->getUser()->getEmail()])
        );
        $publisher($update);

        // Renvoyer une réponse JSON
        return new JsonResponse(['status' => 'Message sent']);
    }

    // Voir un chat
    #[Route('/chat/{id}', name: 'app_chat_view')]
    public function viewChat(Chat $chat, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Récupérer les messages du chat
        $messages = $chat->getMessages();
    
        // Créer le formulaire d'invitation
        $form = $this->createForm(AddUserType::class, null, ['chat' => $chat]);
    
        // Gérer la soumission du formulaire
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer les utilisateurs à inviter
            $usersToInvite = $form->get('users')->getData();

            // Ajouter chaque utilisateur à inviter au chat
            foreach ($usersToInvite as $userToInvite) {
                $chat->addUser($userToInvite);
            }

            // Enregistrer les modifications dans la base de données
            $entityManager->persist($chat);
            $entityManager->flush();

            // Rediriger l'utilisateur vers la page du chat
            return $this->redirectToRoute('app_chat_view', ['id' => $chat->getId()]);
        }
    
        // Rendre la vue du chat
        return $this->render('chat/view.html.twig', [
            'chat' => $chat,
            'messages' => $messages,
            'form' => $form->createView(),
        ]);
    }

    // Lister les chats de l'utilisateur
    #[Route('/chats', name: 'app_my_chats')]
    public function myChats(Security $security): Response
    {
        // Récupérer l'utilisateur actuellement connecté
        $user = $security->getUser();

        // Récupérer les chats de l'utilisateur
        $chats = $user->getChats();

        // Rendre la vue des chats de l'utilisateur
        return $this->render('chat/chats.html.twig', [
            'chats' => $chats,
        ]);
    }
}