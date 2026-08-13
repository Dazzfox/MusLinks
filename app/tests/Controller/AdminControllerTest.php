<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
{
    // Doit correspondre à ADMIN_PIN_HASH dans .env.test (sha256('424242')) — PIN de test
    // dédié, jamais le vrai PIN de prod.
    private const VALID_TEST_PIN = '424242';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        // Un seul createClient() par test — Symfony refuse d'en démarrer un second.
        $this->client = static::createClient();
        // Repart d'un compteur de tentatives à zéro à chaque test, sinon les tests
        // de verrouillage se contaminent entre eux (même cache.app partagé).
        self::getContainer()->get('cache.app')->clear();
    }

    private function submitPin(string $pin): void
    {
        $this->client->request('GET', '/admin/login');
        $this->client->submitForm('Accéder au tableau de bord', ['digits' => str_split($pin)]);
    }

    public function testLoginPageLoads(): void
    {
        $this->client->request('GET', '/admin/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.pin-digit');
    }

    public function testWrongPinShowsError(): void
    {
        $this->submitPin('000000');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Code PIN incorrect');
    }

    public function testCorrectPinLogsIn(): void
    {
        $this->submitPin(self::VALID_TEST_PIN);

        $this->assertResponseRedirects('/admin/dashboard');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Tableau de bord');
    }

    public function testLocksOutAfterFiveFailedAttempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->submitPin('000000');
        }

        // 6e tentative, même avec le BON PIN : doit rester bloquée.
        $this->submitPin(self::VALID_TEST_PIN);

        $this->assertResponseIsSuccessful(); // pas de redirection vers le dashboard
        $this->assertSelectorTextContains('body', 'Trop de tentatives');
    }

    public function testSuccessfulLoginResetsAttemptCounter(): void
    {
        // 2 échecs, puis un succès.
        $this->submitPin('111111');
        $this->submitPin('111111');
        $this->submitPin(self::VALID_TEST_PIN);
        $this->assertResponseRedirects('/admin/dashboard');

        // Une session fraîche (cookies vidés) doit pouvoir se reconnecter sans être
        // affectée par un éventuel reliquat — le compteur associé à cette IP a été remis
        // à zéro par la connexion réussie précédente.
        $this->client->getCookieJar()->clear();
        $this->submitPin(self::VALID_TEST_PIN);
        $this->assertResponseRedirects('/admin/dashboard');
    }

    public function testDashboardRedirectsToLoginWhenNotAuthenticated(): void
    {
        $this->client->request('GET', '/admin/dashboard');

        $this->assertResponseRedirects('/admin/login');
    }
}
