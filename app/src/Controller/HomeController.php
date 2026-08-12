<?php

namespace App\Controller;

use App\Entity\Professional;
use App\Repository\ProfessionalRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ProfessionalRepository $repo): Response
    {
        return $this->render('home/index.html.twig', [
            'featured' => $repo->findFeatured(4),
            'stats' => [
                'actifs' => $repo->countByStatut(Professional::STATUT_ACTIF),
                'metiers' => $repo->countMetiers(),
                'villes' => $repo->countVilles(),
            ],
        ]);
    }

    #[Route('/recherche', name: 'app_search')]
    public function recherche(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        return $this->render('search/index.html.twig', [
            'query' => $request->query->get('q', ''),
            'ville' => $request->query->get('ville', ''),
            'domaine' => $request->query->get('domaine', ''),
        ]);
    }

    #[Route('/carte', name: 'app_map')]
    public function carte(): Response
    {
        return $this->render('map/index.html.twig');
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $nom     = htmlspecialchars(trim($request->request->get('nom', '')));
            $email   = filter_var(trim($request->request->get('email', '')), FILTER_VALIDATE_EMAIL);
            $sujet   = htmlspecialchars(trim($request->request->get('sujet', 'question')));
            $message = htmlspecialchars(trim($request->request->get('message', '')));

            if ($email && $nom && $message) {
                try {
                    $mail = (new TemplatedEmail())
                        ->from($_ENV['MAILER_FROM_ADDRESS'])
                        ->to($_ENV['MAILER_CONTACT_ADDRESS'])
                        ->replyTo($email)
                        ->subject('[Contact] ' . $sujet . ' — ' . $nom)
                        ->html('<p><strong>De :</strong> ' . $nom . ' &lt;' . $email . '&gt;</p>'
                            . '<p><strong>Sujet :</strong> ' . $sujet . '</p>'
                            . '<p><strong>Message :</strong></p><p>' . nl2br($message) . '</p>');

                    $mailer->send($mail);
                } catch (\Throwable) {}
            }

            $this->addFlash('success', 'Votre message a bien été envoyé. Nous vous répondrons sous 48h.');
            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig');
    }
}
