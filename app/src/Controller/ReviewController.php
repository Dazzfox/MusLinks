<?php

namespace App\Controller;

use App\Entity\Review;
use App\Form\ReviewType;
use App\Repository\ProfessionalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReviewController extends AbstractController
{
    #[Route('/avis/{id}', name: 'app_review_submit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function submit(int $id, Request $request, ProfessionalRepository $repo, EntityManagerInterface $em): Response
    {
        $professional = $repo->find($id);
        if (!$professional) {
            throw $this->createNotFoundException();
        }

        $review = new Review();
        $review->setProfessional($professional);

        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $auteur = trim($review->getAuteur());
            if ($auteur === '') {
                $review->setAuteur('Anonyme');
            }

            $note = max(1, min(5, (int) ($request->request->all('review')['note'] ?? 5)));
            $review->setNote($note);
            $review->setIsApproved(false);

            $em->persist($review);
            $em->flush();

            $this->addFlash('success', 'Votre avis a été soumis et sera visible après validation. Merci !');
        } else {
            $this->addFlash('danger', 'Une erreur est survenue. Veuillez vérifier les champs.');
        }

        return $this->redirectToRoute('app_professional_show', ['id' => $id]);
    }
}
