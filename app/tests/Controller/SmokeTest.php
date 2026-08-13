<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que les pages principales du site répondent sans erreur serveur — n'attrape pas
 * les bugs fonctionnels fins, mais aurait attrapé par exemple le crash historique du
 * dashboard admin (propriété supprimée référencée dans le template) ou un template Twig
 * cassé après une modification.
 */
class SmokeTest extends WebTestCase
{
    /** @dataProvider publicPageProvider */
    public function testPublicPageRespondsSuccessfully(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $this->assertResponseIsSuccessful("La page $url devrait répondre 200.");
    }

    public static function publicPageProvider(): array
    {
        return [
            'accueil' => ['/'],
            'recherche' => ['/recherche'],
            'carte' => ['/carte'],
            'contact' => ['/contact'],
            'don' => ['/soutenir'],
            'inscription (choix)' => ['/inscription'],
            'inscription commerce physique' => ['/inscription/commerce-physique'],
            'inscription boutique en ligne' => ['/inscription/boutique-en-ligne'],
            'mentions légales' => ['/mentions-legales'],
            'cgu' => ['/cgu'],
            'confidentialité' => ['/politique-de-confidentialite'],
            'faq' => ['/faq'],
            'connexion admin' => ['/admin/login'],
        ];
    }

    /** @dataProvider searchApiProvider */
    public function testSearchApiRespondsWithValidJsonShape(array $query): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/search?' . http_build_query($query));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('hasMore', $data);
        $this->assertIsArray($data['results']);
    }

    public static function searchApiProvider(): array
    {
        return [
            'sans filtre' => [[]],
            'par mot-clé' => [['q' => 'test']],
            'par ville' => [['ville' => '75000']],
            'par domaine' => [['domaine' => 'Santé']],
            'page 2' => [['page' => '2']],
        ];
    }

    public function testSuggestionsApiRespondsWithValidJsonShape(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/suggestions?q=med');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('professions', $data);
        $this->assertArrayHasKey('villes', $data);
    }

    public function testProfessionalsMapApiRespondsWithValidJsonShape(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/professionals');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testUnknownProfessionalReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/professionnel/999999999');

        $this->assertResponseStatusCodeSame(404);
    }
}
