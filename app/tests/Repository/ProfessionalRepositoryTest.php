<?php

namespace App\Tests\Repository;

use App\Entity\Professional;
use App\Repository\ProfessionalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProfessionalRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ProfessionalRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        /** @var ProfessionalRepository $repo */
        $repo = $this->em->getRepository(Professional::class);
        $this->repo = $repo;

        // TRUNCATE (pas DELETE) : un DELETE répété désynchronise l'index FULLTEXT InnoDB au
        // fil des tests (les recherches finissent par ne plus rien matcher du tout, y compris
        // des mots pourtant présents) — bug réel rencontré en écrivant ces tests. TRUNCATE
        // reconstruit proprement la table et son index. FK désactivées le temps de l'opération
        // (review référence professional).
        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $connection->executeStatement('TRUNCATE TABLE professional');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function createPro(array $overrides = []): Professional
    {
        $pro = new Professional();
        $pro->setNomSociete($overrides['nomSociete'] ?? 'Société Test');
        $pro->setDomaineActivite($overrides['domaineActivite'] ?? 'Santé');
        $pro->setProfession($overrides['profession'] ?? 'Médecin généraliste');
        $pro->setSiret($overrides['siret'] ?? null);
        $pro->setAdresseRue($overrides['adresseRue'] ?? '1 rue de Test');
        $pro->setCodePostal($overrides['codePostal'] ?? '75001');
        $pro->setVille($overrides['ville'] ?? 'Paris');
        $pro->setTelephone($overrides['telephone'] ?? '0600000000');
        $pro->setEmail($overrides['email'] ?? uniqid('test', true) . '@example.com');
        $pro->setDescription($overrides['description'] ?? null);
        $pro->setType($overrides['type'] ?? Professional::TYPE_PHYSIQUE);
        $pro->setGenre($overrides['genre'] ?? null);
        $pro->setPays($overrides['pays'] ?? 'FR');
        $pro->setStatut($overrides['statut'] ?? Professional::STATUT_ACTIF);
        $pro->setIsVisible($overrides['isVisible'] ?? true);

        $this->em->persist($pro);

        return $pro;
    }

    public function testKeywordSearchFindsMatchingProfessional(): void
    {
        $this->createPro(['nomSociete' => 'Boucherie El Amine', 'domaineActivite' => 'Alimentation', 'profession' => 'Boucher']);
        $this->em->flush();

        $search = $this->repo->searchJson(query: 'boucher');

        $this->assertSame(1, $search['total']);
        $this->assertCount(1, $search['results']);
        $this->assertSame('Boucherie El Amine', $search['results'][0]->getNomSociete());
    }

    public function testSearchIsCaseAndAccentInsensitive(): void
    {
        $this->createPro(['nomSociete' => 'Cabinet Médical', 'profession' => 'Médecin généraliste']);
        $this->em->flush();

        $search = $this->repo->searchJson(query: 'MEDECIN');

        $this->assertCount(1, $search['results']);
    }

    public function testFalsePositiveGuardRail(): void
    {
        // Bug réel corrigé le 2026-08-11 : recherche "médecin" + ville sans médecin sur
        // place retombait sur une pharmacie ("médecine naturelle" dans sa description) via
        // le repli en préfixe FULLTEXT. Le garde-fou vérifie qu'un vrai médecin existe
        // ailleurs en base (ici Paris) avant de conclure "0 résultat" plutôt que d'élargir
        // la recherche jusqu'à la pharmacie.
        $this->createPro([
            'nomSociete' => 'Cabinet Médical Paris',
            'domaineActivite' => 'Santé',
            'profession' => 'Médecin généraliste',
            'ville' => 'Paris',
            'codePostal' => '75001',
        ]);
        $this->createPro([
            'nomSociete' => 'Pharmacie Test',
            'domaineActivite' => 'Santé',
            'profession' => 'Pharmacien',
            'description' => 'Conseils en phytothérapie et médecine naturelle.',
            'ville' => 'Toulouse',
            'codePostal' => '31000',
        ]);
        $this->em->flush();

        $search = $this->repo->searchJson(query: 'médecin', ville: '31000');

        $this->assertSame(0, $search['total'], 'La pharmacie ne doit pas remonter sur une recherche "médecin" filtrée sur une ville sans médecin réel.');
    }

    public function testWidensToPrefixWhenWordAbsentEverywhere(): void
    {
        // Contrepartie du garde-fou ci-dessus : si le mot n'existe nulle part (recherche
        // tapée en partie, ex. "bouch"), on élargit bien au préfixe FULLTEXT plutôt que de
        // renvoyer 0 résultat à tort.
        $this->createPro(['nomSociete' => 'Boucherie El Amine', 'domaineActivite' => 'Alimentation', 'profession' => 'Boucher']);
        $this->em->flush();

        $search = $this->repo->searchJson(query: 'bouch');

        $this->assertCount(1, $search['results']);
    }

    public function testDepartmentFallbackWhenExactPostalCodeEmpty(): void
    {
        $this->createPro([
            'nomSociete' => 'Plomberie Haddad',
            'domaineActivite' => 'Bâtiment & Travaux',
            'profession' => 'Plombier chauffagiste',
            'ville' => 'Toulouse',
            'codePostal' => '31500',
        ]);
        $this->em->flush();

        // Recherché à 31000 (aucune fiche exactement là) : doit replier sur le département 31.
        $search = $this->repo->searchJson(query: 'plombier', ville: '31000');

        $this->assertCount(1, $search['results']);
        $this->assertSame('Plomberie Haddad', $search['results'][0]->getNomSociete());
    }

    public function testDomaineFilterAlone(): void
    {
        $this->createPro(['nomSociete' => 'Mosquée Test', 'domaineActivite' => 'Mosquée & Religion', 'profession' => 'Mosquée']);
        $this->createPro(['nomSociete' => 'Boucherie Test', 'domaineActivite' => 'Alimentation', 'profession' => 'Boucher']);
        $this->em->flush();

        $search = $this->repo->searchJson(domaine: 'Mosquée & Religion');

        $this->assertCount(1, $search['results']);
        $this->assertSame('Mosquée Test', $search['results'][0]->getNomSociete());
    }

    public function testInvisibleOrInactiveProfessionalsAreExcluded(): void
    {
        $this->createPro(['nomSociete' => 'Fiche invisible', 'isVisible' => false]);
        $this->createPro(['nomSociete' => 'Fiche radiée', 'statut' => Professional::STATUT_RADIE]);
        $this->createPro(['nomSociete' => 'Fiche active', 'isVisible' => true, 'statut' => Professional::STATUT_ACTIF]);
        $this->em->flush();

        $search = $this->repo->searchJson();

        $this->assertCount(1, $search['results']);
        $this->assertSame('Fiche active', $search['results'][0]->getNomSociete());
    }

    public function testPaginationReturnsCorrectTotalAndSlices(): void
    {
        // La base n'est plus plafonnée à un maximum arbitraire de résultats joignables —
        // avec 25 fiches réelles, le total doit refléter les 25, pas être tronqué.
        for ($i = 1; $i <= 25; $i++) {
            $this->createPro(['nomSociete' => 'Boucherie ' . $i, 'domaineActivite' => 'Alimentation', 'profession' => 'Boucher']);
        }
        $this->em->flush();

        $page1 = $this->repo->searchJson(domaine: 'Alimentation', offset: 0, limit: 16);
        $this->assertSame(25, $page1['total']);
        $this->assertCount(16, $page1['results']);

        $page2 = $this->repo->searchJson(domaine: 'Alimentation', offset: 16, limit: 16);
        $this->assertSame(25, $page2['total']);
        $this->assertCount(9, $page2['results']);

        // Les deux pages ne doivent jamais se chevaucher.
        $idsPage1 = array_map(fn (Professional $p) => $p->getId(), $page1['results']);
        $idsPage2 = array_map(fn (Professional $p) => $p->getId(), $page2['results']);
        $this->assertEmpty(array_intersect($idsPage1, $idsPage2));
    }

    public function testPaginationWorksOnFulltextPathToo(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->createPro(['nomSociete' => 'Boucherie Halal ' . $i, 'domaineActivite' => 'Alimentation', 'profession' => 'Boucher halal']);
        }
        $this->em->flush();

        $page1 = $this->repo->searchJson(query: 'boucher', offset: 0, limit: 16);
        $this->assertSame(20, $page1['total']);
        $this->assertCount(16, $page1['results']);

        $page2 = $this->repo->searchJson(query: 'boucher', offset: 16, limit: 16);
        $this->assertCount(4, $page2['results']);
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }
}
