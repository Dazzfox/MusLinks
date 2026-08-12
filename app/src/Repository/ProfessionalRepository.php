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

    private const SEARCH_ALLOWED_TRI = ['nomSociete', 'ville', 'profession', 'starsAverage', 'createdAt'];

    // Plafond de sécurité sur ce que la requête SQL ramène en mémoire — la pagination
    // réelle (page/perPage) se fait ensuite côté contrôleur sur ce tableau déjà chargé.
    private const MAX_RESULTS = 200;

    public function searchJson(
        string $query = '',
        string $ville = '',
        string $genre = '',
        string $type = '',
        string $pays = '',
        string $tri = 'createdAt',
        string $domaine = ''
    ): array {
        $tri = in_array($tri, self::SEARCH_ALLOWED_TRI, true) ? $tri : 'createdAt';
        $words = $query !== '' ? $this->extractSearchWords($query) : [];
        // Code postal complet (31100) : on essaie d'abord la ville exacte, puis en repli le
        // département (31) si rien n'y est trouvé — plutôt que de renvoyer "0 résultat" sec.
        $villeCandidates = $this->villeCandidates($ville);

        if ($words !== []) {
            $exact = implode(' ', array_map(fn (string $w) => '+' . $w, $words));

            foreach ($villeCandidates as $candidateVille) {
                $ids = $this->runFulltextQuery($exact, $candidateVille, $genre, $type, $pays, $domaine);
                if ($ids !== []) {
                    return $this->hydrateInRelevanceOrder($ids, $tri);
                }
            }

            // Le(s) mot(s) exact(s) existent-ils ailleurs dans le fichier, tous filtres mis à
            // part ? Si oui, un vrai résultat a juste été éliminé par la ville/le domaine/etc.
            // (au-delà du repli département déjà tenté ci-dessus) : "0 résultat" est la bonne
            // réponse, on n'élargit pas plus loin. Sans ce garde-fou, élargir (au préfixe puis à
            // une recherche par sous-chaîne) remonterait des faux positifs non liés — ex.
            // "médecin" élargi matcherait "médecine" dans la description d'une pharmacie, alors
            // qu'il n'y a simplement aucun médecin dans le secteur demandé. On n'élargit que si
            // le mot est réellement absent du fichier (ex. recherche tapée en partie, "bouch").
            if ($this->runFulltextQuery($exact, '', '', '', '', '') !== []) {
                return [];
            }

            $prefix = implode(' ', array_map(fn (string $w) => '+' . $w . '*', $words));
            foreach ($villeCandidates as $candidateVille) {
                $ids = $this->runFulltextQuery($prefix, $candidateVille, $genre, $type, $pays, $domaine);
                if ($ids !== []) {
                    return $this->hydrateInRelevanceOrder($ids, $tri);
                }
            }

            // Le mot est resté introuvable même en préfixe FULLTEXT (typiquement trop court pour
            // l'index — MySQL ignore par défaut les mots de moins de 3 caractères). On tombe sur
            // la recherche par sous-chaîne classique ci-dessous, seul filet de sécurité restant
            // pour ce cas précis (le garde-fou ci-dessus a déjà écarté le cas "filtré à zéro").
        }

        foreach ($villeCandidates as $i => $candidateVille) {
            $results = $this->likeSearch($query, $candidateVille, $genre, $type, $pays, $domaine, $tri);
            $isLastCandidate = $i === array_key_last($villeCandidates);
            if ($results !== [] || $isLastCandidate) {
                return $results;
            }
        }

        return [];
    }

    /**
     * Code postal à 5 chiffres → [code exact, département (2 premiers chiffres)] pour permettre
     * un repli "aucun résultat à cette adresse précise, mais peut-être dans le département".
     * Toute autre saisie (ville en toutes lettres, département déjà saisi, vide…) : inchangée.
     */
    private function villeCandidates(string $ville): array
    {
        if (!preg_match('/^\d{5}$/', $ville)) {
            return [$ville];
        }

        $departement = substr($ville, 0, 2);

        return [$ville, $departement];
    }

    private function likeSearch(string $query, string $ville, string $genre, string $type, string $pays, string $domaine, string $tri): array
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

        if ($domaine !== '') {
            $qb->andWhere('p.domaineActivite = :domaine')
               ->setParameter('domaine', $domaine);
        }

        $direction = $tri === 'starsAverage' ? 'DESC' : 'ASC';
        $qb->orderBy('p.' . $tri, $direction);

        return $qb->setMaxResults(self::MAX_RESULTS)->getQuery()->getResult();
    }

    /**
     * Nettoie et découpe la requête en mots utilisables pour le mode booléen FULLTEXT (mots de 2
     * caractères ou plus, opérateurs booléens MySQL neutralisés).
     */
    private function extractSearchWords(string $query): array
    {
        $words = preg_split('/\s+/', trim($this->normalize($query)), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(array_map(
            fn (string $w) => preg_replace('/[+\-<>()~*"@]+/', '', $w),
            $words
        ), fn (string $w) => mb_strlen($w) >= 2));
    }

    private function runFulltextQuery(string $boolean, string $ville, string $genre, string $type, string $pays, string $domaine): array
    {
        $match = 'MATCH(nom_societe, profession, domaine_activite, description) AGAINST (:q IN BOOLEAN MODE)';
        $sql = "SELECT id, $match AS relevance FROM professional
                WHERE statut = :statut AND is_visible = 1 AND $match > 0";
        $params = ['q' => $boolean, 'statut' => Professional::STATUT_ACTIF];

        if ($ville !== '') {
            $sql .= ' AND type = :physType';
            $params['physType'] = Professional::TYPE_PHYSIQUE;

            if (preg_match('/^\d{2,5}$/', $ville)) {
                $sql .= ' AND code_postal LIKE :villeParam';
                $params['villeParam'] = $ville . '%';
            } else {
                $sql .= ' AND (ville LIKE :villeParam OR code_postal LIKE :villeParam)';
                $params['villeParam'] = '%' . $ville . '%';
            }
        }

        if ($genre !== '') {
            $sql .= ' AND genre = :genre';
            $params['genre'] = $genre;
        }

        if ($type !== '') {
            $sql .= ' AND type = :type';
            $params['type'] = $type;
        }

        if ($pays !== '') {
            $sql .= ' AND pays = :pays';
            $params['pays'] = $pays;
        }

        if ($domaine !== '') {
            $sql .= ' AND domaine_activite = :domaine';
            $params['domaine'] = $domaine;
        }

        $sql .= ' ORDER BY relevance DESC LIMIT ' . self::MAX_RESULTS;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();

        return array_map('intval', array_column($rows, 'id'));
    }

    /**
     * Réhydrate les entités par ID en conservant l'ordre de pertinence — sauf si l'utilisateur a
     * explicitement choisi un autre tri (note, nom, ville), auquel cas cet ordre prime.
     */
    private function hydrateInRelevanceOrder(array $ids, string $tri): array
    {
        $professionals = $this->createQueryBuilder('p')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        if (in_array($tri, ['starsAverage', 'nomSociete', 'ville'], true)) {
            $getter = 'get' . ucfirst($tri);
            $direction = $tri === 'starsAverage' ? -1 : 1;
            usort($professionals, fn (Professional $a, Professional $b) => $direction * ($a->$getter() <=> $b->$getter()));

            return $professionals;
        }

        $relevanceOrder = array_flip($ids);
        usort($professionals, fn (Professional $a, Professional $b) => $relevanceOrder[$a->getId()] <=> $relevanceOrder[$b->getId()]);

        return $professionals;
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
