<?php

namespace App\Repository;

use App\Entity\LieuImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LieuImage>
 */
class LieuImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LieuImage::class);
    }

    /**
     * @return LieuImage[]
     */
    public function findByLieu(int $lieuId): array
    {
        return $this->createQueryBuilder('li')
            ->where('li.lieu = :lieuId')
            ->setParameter('lieuId', $lieuId)
            ->orderBy('li.ordre', 'ASC')
            ->addOrderBy('li.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
