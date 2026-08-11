<?php

namespace App\Controller;

use App\Repository\ProfessionalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/suggestions', name: 'api_suggestions', methods: ['GET'])]
    public function suggestions(Request $request, ProfessionalRepository $repo): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        $ville = trim($request->query->get('ville', ''));

        $data = ['professions' => [], 'villes' => []];

        if (strlen($q) >= 1) {
            $data['professions'] = $repo->findKeywordSuggestions($q);
        }

        if (strlen($ville) >= 2) {
            $data['villes'] = $repo->findVilleSuggestions($ville);
        }

        return new JsonResponse($data);
    }

    #[Route('/search', name: 'api_search', methods: ['GET'])]
    public function search(Request $request, ProfessionalRepository $repo): JsonResponse
    {
        $query = trim($request->query->get('q', ''));
        $ville = trim($request->query->get('ville', ''));
        $genre = trim($request->query->get('genre', ''));
        $type  = trim($request->query->get('type', ''));
        $pays  = trim($request->query->get('pays', ''));

        $professionals = $repo->searchJson($query, $ville, $genre, $type, $pays);

        $results = array_map(fn($p) => [
            'id'             => $p->getId(),
            'type'           => $p->getType(),
            'nomSociete'     => $p->getNomSociete(),
            'profession'     => $p->getProfession(),
            'domaineActivite'=> $p->getDomaineActivite(),
            'ville'          => $p->getVille(),
            'codePostal'     => $p->getCodePostal(),
            'telephone'      => $p->getTelephone(),
            'siteEcommerce'  => $p->getSiteEcommerce(),
            'starsAverage'   => $p->getStarsAverage(),
            'totalAvis'      => $p->getTotalAvis(),
            'initials'       => $p->getInitials(),
            'siretFormatted' => $p->getSiretFormatted(),
            'imageName'      => $p->getImageName(),
            'pays'           => $p->getPays(),
            'url'            => $this->generateUrl('app_professional_show', ['id' => $p->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'mapsUrl'        => 'https://www.google.com/maps/search/?api=1&query=' . urlencode($p->getAdresseRue() . ' ' . $p->getVille() . ' ' . $p->getCodePostal()),
        ], $professionals);

        return new JsonResponse(['results' => $results, 'count' => count($results)]);
    }

    #[Route('/professionals', name: 'api_professionals', methods: ['GET'])]
    public function professionals(ProfessionalRepository $repo): JsonResponse
    {
        $professionals = $repo->findForMap();

        $results = array_map(fn($p) => [
            'id' => $p->getId(),
            'nomSociete' => $p->getNomSociete(),
            'profession' => $p->getProfession(),
            'domaineActivite' => $p->getDomaineActivite(),
            'ville' => $p->getVille(),
            'latitude' => $p->getLatitude(),
            'longitude' => $p->getLongitude(),
            'starsAverage' => $p->getStarsAverage(),
            'totalAvis' => $p->getTotalAvis(),
            'url' => $this->generateUrl('app_professional_show', ['id' => $p->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ], $professionals);

        return new JsonResponse($results);
    }

    #[Route('/geocode', name: 'api_geocode', methods: ['GET'])]
    public function geocode(Request $request): JsonResponse
    {
        $address = $request->query->get('address', '');
        if (empty($address)) {
            return new JsonResponse(['error' => 'Adresse manquante'], 400);
        }

        $url = 'https://nominatim.openstreetmap.org/search?format=json&accept-language=fr&limit=1&q=' . urlencode($address . ', France');

        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: MusLinks/1.0\r\n",
                'timeout' => 5,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return new JsonResponse(['error' => 'Géocodage indisponible'], 503);
        }

        $data = json_decode($response, true);
        if (empty($data)) {
            return new JsonResponse(['error' => 'Adresse non trouvée'], 404);
        }

        return new JsonResponse([
            'lat' => (float) $data[0]['lat'],
            'lon' => (float) $data[0]['lon'],
        ]);
    }
}
