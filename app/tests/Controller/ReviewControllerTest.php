<?php

namespace App\Tests\Controller;

use App\Entity\Professional;
use App\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ReviewControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        // createClient() doit démarrer le kernel en premier, avant tout getContainer().
        $this->client = static::createClient();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        // TRUNCATE (pas DELETE) : évite de désynchroniser l'index FULLTEXT InnoDB de
        // professional au fil des runs — cf. ProfessionalRepositoryTest pour le détail.
        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $connection->executeStatement('TRUNCATE TABLE review');
        $connection->executeStatement('TRUNCATE TABLE professional');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function createVisiblePro(): Professional
    {
        $pro = new Professional();
        $pro->setNomSociete('Cabinet Test');
        $pro->setDomaineActivite('Santé');
        $pro->setProfession('Médecin généraliste');
        $pro->setNomResponsable('Dupont');
        $pro->setPrenomResponsable('Jean');
        $pro->setAdresseRue('1 rue de Test');
        $pro->setCodePostal('75001');
        $pro->setVille('Paris');
        $pro->setTelephone('0600000000');
        $pro->setEmail(uniqid('test', true) . '@example.com');
        $pro->setStatut(Professional::STATUT_ACTIF);
        $pro->setIsVisible(true);

        $this->em->persist($pro);
        $this->em->flush();

        return $pro;
    }

    public function testSubmittingAReviewPersistsItAsPendingWithCorrectNote(): void
    {
        // Régression du bug historique : $request->request->get('review')['note'] était
        // rejeté par Symfony (accès tableau invalide) et faisait planter la soumission
        // en 400 — corrigé avec $request->request->all('review')['note'].
        $pro = $this->createVisiblePro();

        $this->client->request('GET', '/professionnel/' . $pro->getId());
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('Envoyer mon avis', [
            'review[auteur]' => 'Testeur',
            'review[commentaire]' => 'Très bon accueil, je recommande.',
            'review[note]' => '4',
        ]);

        $this->assertResponseRedirects('/professionnel/' . $pro->getId());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'soumis et sera visible après validation');

        /** @var Review|null $review */
        $review = $this->em->getRepository(Review::class)->findOneBy(['professional' => $pro]);
        $this->assertNotNull($review, 'L\'avis aurait dû être enregistré en base.');
        $this->assertSame(4, $review->getNote());
        $this->assertFalse($review->isApproved(), 'Un avis fraîchement soumis doit rester en attente de modération.');
        $this->assertSame('Testeur', $review->getAuteur());
    }

    public function testEmptyAuteurDefaultsToAnonyme(): void
    {
        $pro = $this->createVisiblePro();

        $this->client->request('GET', '/professionnel/' . $pro->getId());
        $this->client->submitForm('Envoyer mon avis', [
            'review[auteur]' => '',
            'review[commentaire]' => 'Tout à fait correct, rien à signaler.',
            'review[note]' => '3',
        ]);

        /** @var Review|null $review */
        $review = $this->em->getRepository(Review::class)->findOneBy(['professional' => $pro]);
        $this->assertNotNull($review);
        $this->assertSame('Anonyme', $review->getAuteur());
    }

    public function testOutOfRangeNoteIsRejected(): void
    {
        // Une note hors bornes (falsifiée côté client, le champ note est un simple champ
        // caché) est rejetée par la contrainte Assert\Range de l'entité — le formulaire
        // n'est jamais valide, donc aucun avis n'est enregistré.
        $pro = $this->createVisiblePro();

        $this->client->request('GET', '/professionnel/' . $pro->getId());
        $this->client->submitForm('Envoyer mon avis', [
            'review[auteur]' => 'Testeur',
            'review[commentaire]' => 'Une note falsifiée ne doit pas passer.',
            'review[note]' => '99',
        ]);

        $review = $this->em->getRepository(Review::class)->findOneBy(['professional' => $pro]);
        $this->assertNull($review, 'Une note hors bornes ne doit jamais être enregistrée.');
    }
}
