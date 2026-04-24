<?php

namespace App\Repository;

use App\Entity\Lieu;
use App\Enum\LieuCategorie;
use App\Enum\LieuType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lieu>
 */
class LieuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lieu::class);
    }

    // -------------------------------------------------------------------------
    // Utilisé par LieuController (admin) - pagination + filtres
    // -------------------------------------------------------------------------

    /**
     * Retourne un Paginator filtré par recherche, catégorie, type, tri.
     *
     * @param array{q:string, categorie:?LieuCategorie, type:?LieuType, sort:string, dir:string} $filters
     */
    public function paginateFiltered(array $filters, int $page, int $perPage): Paginator
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.lieuHoraire', 'h')
            ->addSelect('h');

        if ($filters['q'] !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(l.nom)', ':q'),
                    $qb->expr()->like('LOWER(l.ville)', ':q'),
                    $qb->expr()->like('LOWER(l.adresse)', ':q'),
                )
            )->setParameter('q', '%'.mb_strtolower($filters['q']).'%');
        }

        if ($filters['categorie'] instanceof LieuCategorie) {
            $qb->andWhere('l.categorie = :categorie')
               ->setParameter('categorie', $filters['categorie']);
        }

        if ($filters['type'] instanceof LieuType) {
            $qb->andWhere('l.type = :type')
               ->setParameter('type', $filters['type']);
        }

        $allowed = ['id', 'nom', 'ville', 'categorie', 'type', 'budget_min', 'budget_max'];
        $sort = in_array($filters['sort'], $allowed, true) ? $filters['sort'] : 'id';
        $dir  = $filters['dir'] === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('l.'.$sort, $dir)
           ->setFirstResult(($page - 1) * $perPage)
           ->setMaxResults($perPage);

        return new Paginator($qb, fetchJoinCollection: false);
    }

    // -------------------------------------------------------------------------
    // Utilisé par show/edit - charge toutes les relations en une requête
    // -------------------------------------------------------------------------

    public function findDetailed(int $id): ?Lieu
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.lieuHoraire', 'h')
            ->leftJoin('l.lieuImages', 'i')
            ->leftJoin('l.evaluationLieu', 'e')
            ->leftJoin('l.offres', 'o')
            ->leftJoin('l.evenements', 'ev')
            ->addSelect('h', 'i', 'e', 'o', 'ev')
            ->where('l.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // -------------------------------------------------------------------------
    // Utilisé par Admin\LieuController (ancien controller du zip)
    // -------------------------------------------------------------------------

    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function search(string $query): array
    {
        $q = '%'.mb_strtolower($query).'%';

        return $this->createQueryBuilder('l')
            ->where('LOWER(l.nom) LIKE :q OR LOWER(l.ville) LIKE :q OR LOWER(l.adresse) LIKE :q')
            ->setParameter('q', $q)
            ->orderBy('l.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCategorie(string $categorie): array
    {
        $cat = LieuCategorie::tryFrom(strtoupper($categorie));
        if ($cat === null) {
            return [];
        }

        return $this->createQueryBuilder('l')
            ->where('l.categorie = :cat')
            ->setParameter('cat', $cat)
            ->orderBy('l.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $type): array
    {
        $t = LieuType::tryFrom(strtoupper($type));
        if ($t === null) {
            return [];
        }

        return $this->createQueryBuilder('l')
            ->where('l.type = :type')
            ->setParameter('type', $t)
            ->orderBy('l.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // -------------------------------------------------------------------------
    // Utilisé par FrontChatbotService
    // -------------------------------------------------------------------------

    /**
     * @return Lieu[]
     */
    public function findAllForChatbot(): array
    {
        return $this->createQueryBuilder('l')
            ->select('l')
            ->orderBy('l.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
