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

        // Table dédiée aux tests, on repart toujours d'une base vide pour des assertions fiables.
        $this->em->getConnection()->executeStatement('DELETE FROM professional');
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

        $results = $this->repo->searchJson(query: 'boucher');

        $this->assertCount(1, $results);
        $this->assertSame('Boucherie El Amine', $results[0]->getNomSociete());
    }

    public function testSearchIsCaseAndAccentInsensitive(): void
    {
        $this->createPro(['nomSociete' => 'Cabinet Médical', 'profession' => 'Médecin généraliste']);
        $this->em->flush();

        $results = $this->repo->searchJson(query: 'MEDECIN');

        $this->assertCount(1, $results);
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

        $results = $this->repo->searchJson(query: 'médecin', ville: '31000');

        $this->assertCount(0, $results, 'La pharmacie ne doit pas remonter sur une recherche "médecin" filtrée sur une ville sans médecin réel.');
    }

    public function testWidensToPrefixWhenWordAbsentEverywhere(): void
    {
        // Contrepartie du garde-fou ci-dessus : si le mot n'existe nulle part (recherche
        // tapée en partie, ex. "bouch"), on élargit bien au préfixe FULLTEXT plutôt que de
        // renvoyer 0 résultat à tort.
        $this->createPro(['nomSociete' => 'Boucherie El Amine', 'domaineActivite' => 'Alimentation', 'profession' => 'Boucher']);
        $this->em->flush();

        $results = $this->repo->searchJson(query: 'bouch');

        $this->assertCount(1, $results);
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
        $results = $this->repo->searchJson(query: 'plombier', ville: '31000');

        $this->assertCount(1, $results);
        $this->assertSame('Plomberie Haddad', $results[0]->getNomSociete());
    }

    public function testDomaineFilterAlone(): void
    {
        $this->createPro(['nomSociete' => 'Mosquée Test', 'domaineActivite' => 'Mosquée & Religion', 'profession' => 'Mosquée']);
        $this->createPro(['nomSociete' => 'Boucherie Test', 'domaineActivite' => 'Alimentation', 'profession' => 'Boucher']);
        $this->em->flush();

        $results = $this->repo->searchJson(domaine: 'Mosquée & Religion');

        $this->assertCount(1, $results);
        $this->assertSame('Mosquée Test', $results[0]->getNomSociete());
    }

    public function testInvisibleOrInactiveProfessionalsAreExcluded(): void
    {
        $this->createPro(['nomSociete' => 'Fiche invisible', 'isVisible' => false]);
        $this->createPro(['nomSociete' => 'Fiche radiée', 'statut' => Professional::STATUT_RADIE]);
        $this->createPro(['nomSociete' => 'Fiche active', 'isVisible' => true, 'statut' => Professional::STATUT_ACTIF]);
        $this->em->flush();

        $results = $this->repo->searchJson();

        $this->assertCount(1, $results);
        $this->assertSame('Fiche active', $results[0]->getNomSociete());
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }
}
