<?php

namespace App\Repository;

use App\Entity\Chat;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Chat>
 *
 * @method Chat|null find($id, $lockMode = null, $lockVersion = null)
 * @method Chat|null findOneBy(array $criteria, array $orderBy = null)
 * @method Chat[]    findAll()
 * @method Chat[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chat::class);
    }

    // Dans cette requête, la sous-requête compte le nombre total d'utilisateurs dans chaque chat.
    // La clause HAVING vérifie ensuite si ce nombre est égal à 2, ce qui garantit que le chat retourné
    // n'a que les deux utilisateurs spécifiés.
    public function findChatByUsers(User $user1, User $user2): ?Chat
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.users', 'u')
            ->where('u.id IN (:ids)')
            ->groupBy('c.id')
            ->having('COUNT(u.id) = 2')
            ->andHaving('(SELECT COUNT(u2.id) FROM App\Entity\User u2 INNER JOIN u2.chats c2 WHERE c2.id = c.id) = 2')
            ->setParameter('ids', [$user1->getId(), $user2->getId()])
            ->getQuery()
            ->getOneOrNullResult();
    }

//    /**
//     * @return Chat[] Returns an array of Chat objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Chat
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
