<?php

namespace App\Controller;

use App\Entity\Professional;
use App\Form\EcommerceType;
use App\Form\ProfessionalType;
use App\Form\ReviewType;
use App\Repository\ProfessionalRepository;
use App\Service\SiretVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProfessionalController extends AbstractController
{
    #[Route('/professionnel/{id}', name: 'app_professional_show', requirements: ['id' => '\d+'])]
    public function show(int $id, ProfessionalRepository $repo): Response
    {
        $professional = $repo->find($id);
        if (!$professional || (!$professional->isVisible() && $professional->getStatut() !== Professional::STATUT_ACTIF)) {
            throw $this->createNotFoundException('Professionnel introuvable.');
        }

        $reviewForm = $this->createForm(ReviewType::class);

        return $this->render('professional/show.html.twig', [
            'professional' => $professional,
            'review_form'  => $reviewForm->createView(),
        ]);
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET'])]
    public function register(): Response
    {
        return $this->render('register/choice.html.twig');
    }

    #[Route('/inscription/commerce-physique', name: 'app_register_physical', methods: ['GET', 'POST'])]
    public function inscription(Request $request, EntityManagerInterface $em, SiretVerifier $siretVerifier, MailerInterface $mailer): Response
    {
        $professional = new Professional();
        $form = $this->createForm(ProfessionalType::class, $professional, [
            'validation_groups' => ['Default', 'physical'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $siret = $professional->getSiret();
            $pays  = $professional->getPays();

            if ($pays === 'FR') {
                if (!$siret || !preg_match('/^\d{14}$/', $siret)) {
                    $this->addFlash('danger', 'Le numéro SIRET est obligatoire pour la France (14 chiffres).');
                    return $this->render('register/index.html.twig', ['form' => $form->createView()]);
                }
                $siretResult = $siretVerifier->verify($siret);
                if ($siretResult['valid'] === false) {
                    $message = match($siretResult['reason']) {
                        'format', 'luhn' => 'Le numéro SIRET est invalide (14 chiffres, clé de contrôle incorrecte).',
                        'not_found'      => 'Ce SIRET n\'est pas répertorié dans le registre national des entreprises.',
                        'closed'         => 'Cette entreprise est radiée du registre — inscription impossible.',
                        default          => 'SIRET non valide.',
                    };
                    $this->addFlash('danger', $message);
                    return $this->render('register/index.html.twig', ['form' => $form->createView()]);
                }
                $professional->setSiretValide($siretResult['valid'] === true);
            } else {
                $professional->setSiretValide(false);
            }

            $professional->setType(Professional::TYPE_PHYSIQUE);
            $professional->setDateInscription(new \DateTimeImmutable());
            $professional->setStatut(Professional::STATUT_EN_ATTENTE);
            $professional->setIsVisible(false);

            $token = bin2hex(random_bytes(32));
            $professional->setEmailVerificationToken($token);

            $em->persist($professional);
            $em->flush();

            $this->sendToFormspree($professional);
            $this->sendVerificationEmail($mailer, $professional, $token);

            return $this->redirectToRoute('app_register_success');
        }

        return $this->render('register/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/inscription/boutique-en-ligne', name: 'app_register_ecommerce', methods: ['GET', 'POST'])]
    public function inscriptionEcommerce(Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $professional = new Professional();
        $professional->setType(Professional::TYPE_ECOMMERCE);

        $form = $this->createForm(EcommerceType::class, $professional);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $professional->setStatut(Professional::STATUT_EN_ATTENTE);
            $professional->setIsVisible(false);
            $professional->setDateInscription(new \DateTimeImmutable());
            $professional->setSiretValide(false);

            $token = bin2hex(random_bytes(32));
            $professional->setEmailVerificationToken($token);

            $em->persist($professional);
            $em->flush();

            $this->sendToFormspree($professional);
            $this->sendVerificationEmail($mailer, $professional, $token);

            return $this->redirectToRoute('app_register_success');
        }

        return $this->render('register/ecommerce.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/inscription/succes', name: 'app_register_success')]
    public function success(): Response
    {
        return $this->render('register/success.html.twig');
    }

    #[Route('/verifier-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(string $token, ProfessionalRepository $repo, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $professional = $repo->findOneBy(['emailVerificationToken' => $token]);

        if (!$professional) {
            return $this->render('register/verify_invalid.html.twig');
        }

        $professional->setEmailVerifiedAt(new \DateTimeImmutable());
        $professional->setEmailVerificationToken(null);

        $autoActivate = $professional->isSiretValide()
            || $professional->getType() === Professional::TYPE_ECOMMERCE
            || $professional->getType() === Professional::TYPE_PHYSIQUE;

        if ($autoActivate) {
            $professional->setStatut(Professional::STATUT_ACTIF);
            $professional->setIsVisible(true);
            $this->sendBienvenueEmail($mailer, $professional);
        }

        $em->flush();

        return $this->render('register/verify_success.html.twig', [
            'auto_activated' => $autoActivate,
            'professional'   => $professional,
        ]);
    }

    private function sendVerificationEmail(MailerInterface $mailer, Professional $professional, string $token): void
    {
        try {
            $link = $this->generateUrl('app_verify_email', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

            $email = (new TemplatedEmail())
                ->from('noreply@muslinks.fr')
                ->to($professional->getEmail())
                ->subject('Confirmez votre adresse email — MusLinks')
                ->htmlTemplate('emails/verification.html.twig')
                ->context([
                    'professional' => $professional,
                    'link'         => $link,
                ]);

            $mailer->send($email);
        } catch (\Throwable) {}
    }

    private function sendBienvenueEmail(MailerInterface $mailer, Professional $professional): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from('noreply@muslinks.fr')
                ->to($professional->getEmail())
                ->subject('🎉 Votre fiche MusLinks est en ligne — ' . $professional->getNomSociete())
                ->htmlTemplate('emails/bienvenue.html.twig')
                ->context([
                    'professional'  => $professional,
                    'dashboard_url' => $this->generateUrl('pro_login', [], UrlGeneratorInterface::ABSOLUTE_URL),
                ]);

            $mailer->send($email);
        } catch (\Throwable) {}
    }

    private function sendToFormspree(Professional $professional): void
    {
        $formspreeUrl = $_ENV['FORMSPREE_URL'] ?? '';
        if (empty($formspreeUrl)) {
            return;
        }

        $data = [
            'type'          => $professional->getType(),
            'nomSociete'    => $professional->getNomSociete(),
            'responsable'   => trim(($professional->getPrenomResponsable() ?? '') . ' ' . ($professional->getNomResponsable() ?? '')),
            'profession'    => $professional->getProfession() ?? $professional->getDomaineActivite(),
            'siret'         => $professional->getSiret() ?? 'N/A',
            'email'         => $professional->getEmail(),
            'telephone'     => $professional->getTelephone() ?? 'N/A',
            'ville'         => trim(($professional->getVille() ?? 'En ligne') . ' ' . ($professional->getCodePostal() ?? '')),
            'siteEcommerce' => $professional->getSiteEcommerce() ?? '',
        ];

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content'       => json_encode($data),
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents($formspreeUrl, false, $context);
    }
}
