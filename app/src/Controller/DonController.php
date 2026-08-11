<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DonController extends AbstractController
{
    #[Route('/soutenir', name: 'app_don')]
    public function index(): Response
    {
        return $this->render('don/index.html.twig', [
            'stripe_public_key' => $_ENV['STRIPE_PUBLIC_KEY'] ?? '',
        ]);
    }

    #[Route('/soutenir/checkout', name: 'app_don_checkout', methods: ['POST'])]
    public function checkout(Request $request): Response
    {
        $stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';

        if (!preg_match('/^sk_(test|live)_/', $stripeSecretKey)) {
            $this->addFlash('danger', 'Stripe non configuré. Contactez-nous à contact@muslinks.fr.');
            return $this->redirectToRoute('app_don');
        }

        $amountEuros = (float) $request->request->get('amount', 1);
        $amountEuros = max(1, min(10000, $amountEuros)); // entre 1€ et 10 000€
        $amountCents = (int) round($amountEuros * 100);

        \Stripe\Stripe::setApiKey($stripeSecretKey);

        try {
            $session = \Stripe\Checkout\Session::create([
                'mode'                 => 'payment',
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => 'eur',
                        'unit_amount'  => $amountCents,
                        'product_data' => [
                            'name'        => 'Don MusLinks',
                            'description' => 'Soutien à la communauté MusLinks — Ensemble, encore plus fort.',
                        ],
                    ],
                ]],
                'success_url' => $this->generateUrl('app_don_merci', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'cancel_url'  => $this->generateUrl('app_don', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->addFlash('danger', 'Le paiement est momentanément indisponible. Merci de réessayer plus tard.');
            return $this->redirectToRoute('app_don');
        }

        return $this->redirect($session->url);
    }

    #[Route('/soutenir/merci', name: 'app_don_merci')]
    public function merci(): Response
    {
        return $this->render('don/merci.html.twig');
    }
}
