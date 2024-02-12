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
        // Vérifier si un utilisateur est connecté
        if (!$security->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Rediriger vers la page de connexion si aucun utilisateur n'est connecté
            return $this->redirectToRoute('app_login');
        }

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

    // Voir un chat
    #[Route('/chat/{id}', name: 'app_chat_view')]
    public function viewChat(Chat $chat, Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        // Récupérer les messages du chat
        $messages = $chat->getMessages();

        // Récupérer l'utilisateur actuellement connecté
        $user = $security->getUser();

        // Récupérer les chats de l'utilisateur
        $chats = $user->getChats();
    
        // Créer le formulaire d'invitation
        // $form = $this->createForm(AddUserType::class, null, ['chat' => $chat]);
    
        // Gérer la soumission du formulaire
        // $form->handleRequest($request);

        // if ($form->isSubmitted() && $form->isValid()) {
        //     // Récupérer les utilisateurs à inviter
        //     $usersToInvite = $form->get('users')->getData();

        //     // Ajouter chaque utilisateur à inviter au chat
        //     foreach ($usersToInvite as $userToInvite) {
        //         $chat->addUser($userToInvite);
        //     }

        //     // Enregistrer les modifications dans la base de données
        //     $entityManager->persist($chat);
        //     $entityManager->flush();

        //     // Rediriger l'utilisateur vers la page du chat
        //     return $this->redirectToRoute('app_chat_view', ['id' => $chat->getId()]);
        // }
    
        // Rendre la vue du chat
        return $this->render('chat/view.html.twig', [
            'chat' => $chat,
            'messages' => $messages,
            'chats' => $chats,
            // 'form' => $form->createView(),
        ]);
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
            json_encode(['message' => $message->getContent(), 'user' => $security->getUser()->getUsername()])
        );
        $publisher($update);

        // Renvoyer une réponse JSON
        return new JsonResponse(['status' => 'Message sent']);
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

    // Kick un utilisateur du chat
    // #[Route('/chat/{id}/kick/{userId}', name: 'app_kick_user')]
    // public function kickUser(Chat $chat, $userId, EntityManagerInterface $entityManager, Security $security): Response
    // {
    //     // Récupérer l'utilisateur à partir de son identifiant
    //     $user = $entityManager->getRepository(User::class)->find($userId);

    //     // Vérifier si l'utilisateur à kick est bien un membre du chat
    //     if (!$chat->getUsers()->contains($user)) {
    //         // Si l'utilisateur à kick n'est pas un membre du chat, renvoyer une erreur 403
    //         return new Response('This user is not a member of this chat', 403);
    //     }

    //     // Retirer l'utilisateur du chat
    //     $chat->removeUser($user);

    //     // Enregistrer les modifications dans la base de données
    //     $entityManager->persist($chat);
    //     $entityManager->flush();

    //     // Rediriger l'utilisateur vers la page des chats
    //     return $this->redirectToRoute('app_chat_view', ['id' => $chat->getId()]);
    // }
}
