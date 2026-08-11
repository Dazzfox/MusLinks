<?php

namespace App\Controller;

use App\Entity\Professional;
use App\Form\ProEditType;
use App\Repository\ProfessionalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/espace-pro')]
class EspaceProController extends AbstractController
{
    private const SESSION_ID      = 'pro_id';
    private const SESSION_EXPIRES = 'pro_expires';
    private const SESSION_TTL     = 10800; // 3 heures
    private const TOKEN_TTL       = 900;   // 15 minutes

    #[Route('/connexion', name: 'pro_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        ProfessionalRepository $repo,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        if ($this->getConnectedPro($request, $repo)) {
            return $this->redirectToRoute('pro_dashboard');
        }

        $sent  = false;
        $error = null;

        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));
            $pro   = $repo->findOneBy(['email' => $email]);

            if ($pro) {
                $token = bin2hex(random_bytes(32));

                $pro->setLoginToken($token);
                $pro->setLoginTokenExpires(new \DateTimeImmutable('+' . self::TOKEN_TTL . ' seconds'));
                $em->flush();

                $link = $this->generateUrl('pro_verify', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

                $mail = (new TemplatedEmail())
                    ->from(new Address('noreply@muslinks.fr', 'MusLinks'))
                    ->to(new Address($pro->getEmail(), $pro->getPrenomResponsable() . ' ' . $pro->getNomResponsable()))
                    ->subject('Votre lien de connexion MusLinks')
                    ->htmlTemplate('emails/magic_link.html.twig')
                    ->context([
                        'professional' => $pro,
                        'link'         => $link,
                        'expires_min'  => self::TOKEN_TTL / 60,
                    ]);

                $mailer->send($mail);
            }

            $sent = true;
        }

        return $this->render('espace_pro/login.html.twig', [
            'sent'  => $sent,
            'error' => $error,
        ]);
    }

    #[Route('/verify/{token}', name: 'pro_verify', methods: ['GET'])]
    public function verify(
        string $token,
        ProfessionalRepository $repo,
        EntityManagerInterface $em,
        Request $request,
    ): Response {
        $pro = $repo->findOneBy(['loginToken' => $token]);

        if (!$pro || !$pro->isLoginTokenValid($token)) {
            return $this->render('espace_pro/link_expired.html.twig');
        }

        $pro->setLoginToken(null);
        $pro->setLoginTokenExpires(null);
        $em->flush();

        $request->getSession()->set(self::SESSION_ID, $pro->getId());
        $request->getSession()->set(self::SESSION_EXPIRES, time() + self::SESSION_TTL);

        return $this->redirectToRoute('pro_dashboard');
    }

    #[Route('/tableau-de-bord', name: 'pro_dashboard')]
    public function dashboard(Request $request, ProfessionalRepository $repo): Response
    {
        $pro = $this->getConnectedPro($request, $repo);
        if (!$pro) {
            return $this->redirectToRoute('pro_login');
        }

        return $this->render('espace_pro/dashboard.html.twig', ['professional' => $pro]);
    }

    #[Route('/modifier', name: 'pro_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ProfessionalRepository $repo, EntityManagerInterface $em): Response
    {
        $pro = $this->getConnectedPro($request, $repo);
        if (!$pro) {
            return $this->redirectToRoute('pro_login');
        }

        $form = $this->createForm(ProEditType::class, $pro);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Vos informations ont été mises à jour avec succès.');
            return $this->redirectToRoute('pro_dashboard');
        }

        return $this->render('espace_pro/edit.html.twig', [
            'professional' => $pro,
            'form'         => $form,
        ]);
    }

    #[Route('/deconnexion', name: 'pro_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $request->getSession()->remove(self::SESSION_ID);
        $request->getSession()->remove(self::SESSION_EXPIRES);
        return $this->redirectToRoute('pro_login');
    }

    private function getConnectedPro(Request $request, ProfessionalRepository $repo): ?Professional
    {
        $proId   = $request->getSession()->get(self::SESSION_ID);
        $expires = $request->getSession()->get(self::SESSION_EXPIRES, 0);

        if (!$proId || time() > $expires) {
            $request->getSession()->remove(self::SESSION_ID);
            $request->getSession()->remove(self::SESSION_EXPIRES);
            return null;
        }

        return $repo->find($proId);
    }
}
