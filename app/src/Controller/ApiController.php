<?php

namespace App\Controller;

use App\Repository\ProfessionalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api')]
class ApiController extends AbstractController
{
    private const GEO_API_URL = 'https://geo.api.gouv.fr/communes';
    private const GEO_API_URL_DEPARTEMENTS = 'https://geo.api.gouv.fr/departements/';

    #[Route('/suggestions', name: 'api_suggestions', methods: ['GET'])]
    public function suggestions(Request $request, ProfessionalRepository $repo, HttpClientInterface $httpClient): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        $ville = trim($request->query->get('ville', ''));

        $data = ['professions' => [], 'villes' => []];

        if (strlen($q) >= 1) {
            $data['professions'] = $repo->findKeywordSuggestions($q);
        }

        if (strlen($ville) >= 2) {
            $data['villes'] = $this->communeSuggestions($ville, $httpClient);
        }

        return new JsonResponse($data);
    }

    /**
     * Suggestions de villes sur toute la France (pas seulement celles où un professionnel
     * est déjà inscrit) via l'API officielle du gouvernement — gratuite, sans clé, données
     * à jour de toutes les communes françaises.
     */
    private function communeSuggestions(string $ville, HttpClientInterface $httpClient): array
    {
        try {
            if (preg_match('/^\d{2,5}$/', $ville)) {
                return $this->communeSuggestionsByPostalPrefix($ville, $httpClient);
            }

            $response = $httpClient->request('GET', self::GEO_API_URL, [
                'query'   => ['nom' => $ville, 'boost' => 'population', 'limit' => 8, 'fields' => 'nom,codesPostaux'],
                'timeout' => 4,
            ]);

            return array_map(
                fn (array $c) => $c['nom'] . ' (' . ($c['codesPostaux'][0] ?? '') . ')',
                $response->toArray(false)
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function communeSuggestionsByPostalPrefix(string $prefix, HttpClientInterface $httpClient): array
    {
        $departement = substr($prefix, 0, 2);

        // /communes?codeDepartement=.. est plafonné à 200 résultats et peut donc rater de
        // grandes villes selon l'ordre renvoyé (ex. Toulouse absente pour le 31, qui compte
        // ~586 communes) — /departements/{code}/communes renvoie la liste complète.
        $response = $httpClient->request('GET', self::GEO_API_URL_DEPARTEMENTS . $departement . '/communes', [
            'query'   => ['fields' => 'nom,codesPostaux,population'],
            'timeout' => 4,
        ]);

        $communes = $response->toArray(false);

        // codeDepartement seul (ex. "31") : tout le département, trié par population.
        // Préfixe plus précis (ex. "310") : uniquement les communes dont un code postal
        // correspond réellement à ce préfixe.
        if (strlen($prefix) > 2) {
            $communes = array_filter(
                $communes,
                fn (array $c) => $this->hasMatchingPostalCode($c['codesPostaux'] ?? [], $prefix)
            );
        }

        usort($communes, fn (array $a, array $b) => ($b['population'] ?? 0) <=> ($a['population'] ?? 0));

        return array_map(
            fn (array $c) => $c['nom'] . ' (' . $this->matchingPostalCode($c['codesPostaux'] ?? [], $prefix) . ')',
            array_slice($communes, 0, 8)
        );
    }

    private function hasMatchingPostalCode(array $codesPostaux, string $prefix): bool
    {
        foreach ($codesPostaux as $cp) {
            if (str_starts_with($cp, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function matchingPostalCode(array $codesPostaux, string $prefix): string
    {
        foreach ($codesPostaux as $cp) {
            if (str_starts_with($cp, $prefix)) {
                return $cp;
            }
        }

        return $codesPostaux[0] ?? '';
    }

    private const RESULTS_PER_PAGE = 16;

    #[Route('/search', name: 'api_search', methods: ['GET'])]
    public function search(Request $request, ProfessionalRepository $repo): JsonResponse
    {
        $query   = trim($request->query->get('q', ''));
        $ville   = trim($request->query->get('ville', ''));
        $genre   = trim($request->query->get('genre', ''));
        $type    = trim($request->query->get('type', ''));
        $pays    = trim($request->query->get('pays', ''));
        $domaine = trim($request->query->get('domaine', ''));
        $tri     = trim($request->query->get('tri', 'createdAt'));
        $page    = max(1, (int) $request->query->get('page', 1));

        $search = $repo->searchJson(
            $query, $ville, $genre, $type, $pays, $tri, $domaine,
            offset: ($page - 1) * self::RESULTS_PER_PAGE,
            limit: self::RESULTS_PER_PAGE,
        );
        $professionals = $search['results'];
        $total = $search['total'];

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

        return new JsonResponse([
            'results'  => $results,
            'count'    => count($results),
            'total'    => $total,
            'page'     => $page,
            'hasMore'  => $total > $page * self::RESULTS_PER_PAGE,
        ]);
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
