<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Item;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    /** @return Item[] */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Item[] */
    public function findUpdatedSince(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.updatedAt > :since')
            ->setParameter('since', $since)
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
