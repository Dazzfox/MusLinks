<?php

namespace App\Repository;

use App\Entity\Professional;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class ProfessionalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Professional::class);
    }

    public function findActifVisible(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.isVisible = :visible')
            ->setParameter('statut', Professional::STATUT_ACTIF)
            ->setParameter('visible', true)
            ->orderBy('p.starsAverage', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFeatured(int $limit = 4): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.isVisible = :visible')
            ->setParameter('statut', Professional::STATUT_ACTIF)
            ->setParameter('visible', true)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function searchQueryBuilder(
        string $query = '',
        string $ville = '',
        string $domaine = '',
        string $tri = 'createdAt',
        string $genre = ''
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.isVisible = :visible')
            ->setParameter('statut', Professional::STATUT_ACTIF)
            ->setParameter('visible', true);

        if ($query !== '') {
            $qb->andWhere('(p.nomSociete LIKE :q OR p.profession LIKE :q OR p.domaineActivite LIKE :q OR p.description LIKE :q)')
               ->setParameter('q', '%' . $query . '%');
        }

        if ($ville !== '') {
            if (preg_match('/^\d{2}$/', $ville)) {
                $qb->andWhere('p.codePostal LIKE :ville')
                   ->setParameter('ville', $ville . '%');
            } else {
                $qb->andWhere('(p.ville LIKE :ville OR p.codePostal LIKE :ville)')
                   ->setParameter('ville', '%' . $ville . '%');
            }
        }

        if ($domaine !== '') {
            $qb->andWhere('p.domaineActivite = :domaine')
               ->setParameter('domaine', $domaine);
        }

        if ($genre !== '') {
            $qb->andWhere('p.genre = :genre')
               ->setParameter('genre', $genre);
        }

        $allowedTri = ['nomSociete', 'ville', 'profession', 'starsAverage', 'createdAt'];
        $tri = in_array($tri, $allowedTri, true) ? $tri : 'createdAt';
        $direction = in_array($tri, ['starsAverage'], true) ? 'DESC' : 'ASC';
        $qb->orderBy('p.' . $tri, $direction);

        return $qb;
    }

    public function searchJson(string $query = '', string $ville = '', string $genre = '', string $type = '', string $pays = ''): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.isVisible = :visible')
            ->setParameter('statut', Professional::STATUT_ACTIF)
            ->setParameter('visible', true);

        if ($query !== '') {
            $q = '%' . $this->normalize($query) . '%';
            $qb->andWhere('(p.nomSociete LIKE :q OR p.profession LIKE :q OR p.domaineActivite LIKE :q OR p.description LIKE :q)')
               ->setParameter('q', $q);
        }

        if ($ville !== '') {
            $qb->andWhere('p.type = :physType')
               ->setParameter('physType', Professional::TYPE_PHYSIQUE);

            if (preg_match('/^\d{2,5}$/', $ville)) {
                $qb->andWhere('p.codePostal LIKE :villeParam')
                   ->setParameter('villeParam', $ville . '%');
            } else {
                $qb->andWhere('(p.ville LIKE :villeParam OR p.codePostal LIKE :villeParam)')
                   ->setParameter('villeParam', '%' . $ville . '%');
            }
        }

        if ($genre !== '') {
            $qb->andWhere('p.genre = :genre')
               ->setParameter('genre', $genre);
        }

        if ($type !== '') {
            $qb->andWhere('p.type = :type')
               ->setParameter('type', $type);
        }

        if ($pays !== '') {
            $qb->andWhere('p.pays = :pays')
               ->setParameter('pays', $pays);
        }

        return $qb->setMaxResults(50)->getQuery()->getResult();
    }

    public function findKeywordSuggestions(string $q, int $limit = 8): array
    {
        $pattern = '%' . $this->normalize($q) . '%';

        $names = $this->createQueryBuilder('p')
            ->select('p.nomSociete AS label')
            ->distinct()
            ->andWhere('p.statut = :statut')->andWhere('p.isVisible = :visible')
            ->andWhere('p.nomSociete LIKE :q')
            ->setParameter('statut', Professional::STATUT_ACTIF)
            ->setParameter('visible', true)
            ->setParameter('q', $pattern)
            ->setMaxResults(4)->getQuery()->getResult();

        $profs = $this->createQueryBuilder('p')
            ->select('p.profession AS label')
            ->distinct()
            ->andWhere('p.statut = :statut')->andWhere('p.isVisible = :visible')
            ->andWhere('p.profession IS NOT NULL')->andWhere('p.profession LIKE :q')
            ->setParameter('statut', Professional::STATUT_ACTIF)
            ->setParameter('visible', true)
            ->setParameter('q', $pattern)
            ->setMaxResults(4)->getQuery()->getResult();

        $domains = $this->createQueryBuilder('p')
            ->select('p.domaineActivite AS label')
            ->distinct()
            ->andWhere('p.statut = :statut')->andWhere('p.isVisible = :visible')
            ->andWhere('p.domaineActivite LIKE :q')
            ->setParameter('statut', Professional::STATUT_ACTIF)
            ->setParameter('visible', true)
            ->setParameter('q', $pattern)
            ->setMaxResults(3)->getQuery()->getResult();

        $all = array_values(array_unique(array_filter(array_merge(
            array_column($names,   'label'),
            array_column($profs,   'label'),
            array_column($domains, 'label'),
        ))));

        return array_slice($all, 0, $limit);
    }

    public function findVilleSuggestions(string $q, int $limit = 8): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.ville, p.codePostal')
            ->distinct()
            ->andWhere('p.statut = :statut')
            ->andWhere('p.isVisible = :visible')
            ->andWhere('p.ville IS NOT NULL')
            ->setParameter('statut', Professional::STATUT_ACTIF)
            ->setParameter('visible', true)
            ->setMaxResults($limit);

        if (preg_match('/^\d{2,5}$/', $q)) {
            $qb->andWhere('p.codePostal LIKE :q')->setParameter('q', $q . '%');
        } else {
            $qb->andWhere('(p.ville LIKE :q OR p.codePostal LIKE :q)')
               ->setParameter('q', '%' . $q . '%');
        }

        return array_map(
            fn($r) => $r['ville'] . ' (' . $r['codePostal'] . ')',
            $qb->getQuery()->getResult()
        );
    }

    private function normalize(string $q): string
    {
        $q = mb_strtolower(trim($q));
        return str_replace(
            ['à','â','ä','é','è','ê','ë','î','ï','ô','ö','ù','û','ü','ç','œ','æ'],
            ['a','a','a','e','e','e','e','i','i','o','o','u','u','u','c','oe','ae'],
            $q
        );
    }

    public function findForMap(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.isVisible = :visible')
            ->andWhere('p.latitude IS NOT NULL')
            ->andWhere('p.longitude IS NOT NULL')
            ->setParameter('statut', Professional::STATUT_ACTIF)
            ->setParameter('visible', true)
            ->getQuery()
            ->getResult();
    }

    public function countByStatut(string $statut): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countRadieCeMois(): int
    {
        $debut = new \DateTimeImmutable('first day of this month midnight');
        $fin = new \DateTimeImmutable('last day of this month 23:59:59');
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.updatedAt BETWEEN :debut AND :fin')
            ->setParameter('statut', Professional::STATUT_RADIE)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countVilles(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.ville)')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', Professional::STATUT_ACTIF)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countMetiers(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.domaineActivite)')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', Professional::STATUT_ACTIF)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
