<?php

namespace App\Controller;

use App\Entity\Professional;
use App\Entity\Review;
use App\Repository\ProfessionalRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    // Protection anti-brute-force sur le PIN (6 chiffres, 1M de combinaisons) : le site
    // tourne sans surveillance permanente, donc pas question de compter sur une détection
    // manuelle d'attaque.
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 900; // 15 minutes

    private function requireAdmin(Request $request): ?RedirectResponse
    {
        $session = $request->getSession();
        if (!$session->get('admin_logged')) {
            return $this->redirectToRoute('admin_login');
        }

        $loggedAt = $session->get('admin_logged_at', 0);
        if (time() - $loggedAt > 10800) {
            $session->clear();
            return $this->redirectToRoute('admin_login');
        }

        if ($request->isMethod('POST') && !$this->isCsrfTokenValid('admin_action', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide ou expiré, merci de réessayer.');
            return $this->redirectToRoute('admin_dashboard');
        }

        return null;
    }

    #[Route('', name: 'admin_index')]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(Request $request, #[Autowire(service: 'cache.app')] CacheItemPoolInterface $cache): Response
    {
        if ($request->getSession()->get('admin_logged')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $ip = $request->getClientIp() ?? 'unknown';
            $attemptsItem = $cache->getItem('admin_login_attempts_' . md5($ip));
            $attempts = $attemptsItem->isHit() ? $attemptsItem->get() : 0;

            if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
                $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
            } else {
                $digits = $request->request->all('digits') ?? [];
                $pin = implode('', array_map('strval', $digits));

                if (strlen($pin) !== 6 || !ctype_digit($pin)) {
                    $error = 'Veuillez saisir un code PIN à 6 chiffres.';
                } else {
                    $pinHash = hash('sha256', $pin);
                    $expectedHash = $_ENV['ADMIN_PIN_HASH'] ?? '';

                    if ($pinHash === $expectedHash) {
                        $cache->deleteItem($attemptsItem->getKey());
                        $session = $request->getSession();
                        $session->set('admin_logged', true);
                        $session->set('admin_logged_at', time());
                        return $this->redirectToRoute('admin_dashboard');
                    }

                    $attempts++;
                    $attemptsItem->set($attempts)->expiresAfter(self::LOCKOUT_SECONDS);
                    $cache->save($attemptsItem);

                    $remaining = self::MAX_LOGIN_ATTEMPTS - $attempts;
                    $error = $remaining > 0
                        ? sprintf('Code PIN incorrect. %d tentative(s) restante(s) avant blocage temporaire de 15 minutes.', $remaining)
                        : 'Trop de tentatives. Réessayez dans 15 minutes.';
                }
            }
        }

        return $this->render('admin/login.html.twig', ['error' => $error]);
    }

    #[Route('/dashboard', name: 'admin_dashboard')]
    public function dashboard(
        Request $request,
        ProfessionalRepository $proRepo,
        ReviewRepository $reviewRepo
    ): Response {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'en_attente' => $proRepo->countByStatut(Professional::STATUT_EN_ATTENTE),
                'actif' => $proRepo->countByStatut(Professional::STATUT_ACTIF),
                'renouvellement' => $proRepo->countByStatut(Professional::STATUT_RENOUVELLEMENT),
                'radie_mois' => $proRepo->countRadieCeMois(),
            ],
            'en_attente' => $proRepo->findByStatut(Professional::STATUT_EN_ATTENTE),
            'actifs' => $proRepo->findByStatut(Professional::STATUT_ACTIF),
            'renouvellements' => $proRepo->findByStatut(Professional::STATUT_RENOUVELLEMENT),
            'radies' => $proRepo->findByStatut(Professional::STATUT_RADIE),
            'avis_pending' => $reviewRepo->findPending(),
            'active_tab' => $request->query->get('tab', 'en_attente'),
        ]);
    }

    #[Route('/valider/{id}', name: 'admin_valider', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function valider(Request $request, Professional $professional, EntityManagerInterface $em): Response
    {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }

        $professional->setStatut(Professional::STATUT_ACTIF);
        $professional->setIsVisible(true);
        $em->flush();

        $this->addFlash('success', sprintf('"%s" validé avec succès.', $professional->getNomSociete()));
        return $this->redirectToRoute('admin_dashboard', ['tab' => 'actifs']);
    }

    #[Route('/refuser/{id}', name: 'admin_refuser', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function refuser(Request $request, Professional $professional, EntityManagerInterface $em): Response
    {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }

        $professional->setStatut(Professional::STATUT_RADIE);
        $professional->setIsVisible(false);
        $em->flush();

        $this->addFlash('info', sprintf('"%s" refusé.', $professional->getNomSociete()));
        return $this->redirectToRoute('admin_dashboard', ['tab' => 'radies']);
    }

    #[Route('/renouveler/{id}', name: 'admin_renouveler', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function renouveler(Request $request, Professional $professional, EntityManagerInterface $em): Response
    {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }

        $professional->setStatut(Professional::STATUT_ACTIF);
        $professional->setIsVisible(true);
        $em->flush();

        $this->addFlash('success', sprintf('"%s" réactivé avec succès.', $professional->getNomSociete()));
        return $this->redirectToRoute('admin_dashboard', ['tab' => 'actifs']);
    }

    #[Route('/reactiver/{id}', name: 'admin_reactiver', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reactiver(Request $request, Professional $professional, EntityManagerInterface $em): Response
    {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }

        $professional->setStatut(Professional::STATUT_EN_ATTENTE);
        $professional->setIsVisible(false);
        $em->flush();

        $this->addFlash('success', sprintf('"%s" remis en attente de validation.', $professional->getNomSociete()));
        return $this->redirectToRoute('admin_dashboard', ['tab' => 'en_attente']);
    }

    #[Route('/supprimer/{id}', name: 'admin_supprimer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function supprimer(Request $request, Professional $professional, EntityManagerInterface $em): Response
    {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }

        $nom = $professional->getNomSociete();
        $em->remove($professional);
        $em->flush();

        $this->addFlash('danger', sprintf('"%s" supprimé définitivement.', $nom));
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/approuver-avis/{id}', name: 'admin_approuver_avis', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approuverAvis(Request $request, Review $review, EntityManagerInterface $em): Response
    {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }

        $review->setIsApproved(true);
        $review->getProfessional()?->updateRatings();
        $em->flush();

        $this->addFlash('success', 'Avis approuvé et publié.');
        return $this->redirectToRoute('admin_dashboard', ['tab' => 'avis']);
    }

    #[Route('/supprimer-avis/{id}', name: 'admin_supprimer_avis', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function supprimerAvis(Request $request, Review $review, EntityManagerInterface $em): Response
    {
        if ($redirect = $this->requireAdmin($request)) {
            return $redirect;
        }

        $em->remove($review);
        $em->flush();

        $this->addFlash('info', 'Avis supprimé.');
        return $this->redirectToRoute('admin_dashboard', ['tab' => 'avis']);
    }

    #[Route('/logout', name: 'admin_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('logout', $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $request->getSession()->clear();
        return $this->redirectToRoute('admin_login');
    }
}
