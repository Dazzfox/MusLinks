<?php

namespace App\Command;

use App\Entity\Professional;
use App\Repository\ProfessionalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Importe les mosquées de France depuis OpenStreetMap (Overpass API) — données
 * ouvertes et légitimes sur des lieux publics, pas de commerce privé impliqué.
 */
#[AsCommand(
    name: 'app:import:mosquees',
    description: 'Importe les mosquées de France depuis OpenStreetMap',
)]
class ImportMosqueesCommand extends Command
{
    private const OVERPASS_URL = 'https://overpass-api.de/api/interpreter';
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/reverse';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly ProfessionalRepository $repo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->writeln("Interrogation d'OpenStreetMap (peut prendre 1 à 2 minutes)...");

        // Les node/way/relation sont interrogés ensemble — certaines mosquées ne sont
        // cartographiées que comme contour de bâtiment (way) ou groupe de bâtiments
        // (relation), pas comme simple point (node), et étaient ratées par la version
        // précédente de cette commande qui ne cherchait que des node. "out center" donne
        // un centre calculé pour les way/relation (les node gardent lat/lon directement).
        $query = <<<OVERPASS
            [out:json][timeout:90];
            area["ISO3166-1"="FR"][admin_level=2]->.fr;
            (
              node["amenity"="place_of_worship"]["religion"="muslim"](area.fr);
              way["amenity"="place_of_worship"]["religion"="muslim"](area.fr);
              relation["amenity"="place_of_worship"]["religion"="muslim"](area.fr);
            );
            out center;
            OVERPASS;

        try {
            $response = $this->httpClient->request('POST', self::OVERPASS_URL, [
                'body'    => ['data' => $query],
                'timeout' => 120,
            ]);
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            $io->error('Impossible de contacter Overpass : ' . $e->getMessage());
            return Command::FAILURE;
        }

        $elements = $data['elements'] ?? [];
        $total = count($elements);
        $io->writeln(sprintf('%d éléments OSM trouvés (node + way + relation).', $total));

        // Un même lieu réel est souvent cartographié à la fois comme node (point) et comme
        // way/relation (contour de bâtiment) — sans ce filtre, ça créerait un doublon pour
        // quasiment chaque mosquée déjà importée. On garde les node en priorité (traités en
        // premier, format d'email historique) et on écarte tout élément trop proche d'un
        // autre déjà retenu — que ce soit dans ce lot ou déjà en base depuis un import
        // précédent. Seuil élargi (150 m) quand le nom est identique : le centre calculé
        // d'un grand bâtiment peut légitimement dériver de 50-100 m par rapport au point
        // d'origine (cas réel constaté : même mosquée, même rue, ~70 m d'écart) — un seuil
        // fixe de 50 m ratait ce genre de doublon.
        usort($elements, fn (array $a, array $b) => ($a['type'] === 'node' ? 0 : 1) <=> ($b['type'] === 'node' ? 0 : 1));

        $acceptedLocations = [];
        foreach ($this->repo->findBy(['domaineActivite' => 'Mosquée & Religion']) as $existing) {
            if ($existing->getLatitude() !== null && $existing->getLongitude() !== null) {
                $acceptedLocations[] = [$existing->getLatitude(), $existing->getLongitude(), $existing->getNomSociete()];
            }
        }

        $created = 0;
        $skipped = 0;
        $incomplete = 0;
        $tropProche = 0;
        $batch = 0;

        foreach ($elements as $i => $element) {
            $tags = $element['tags'] ?? [];
            $osmId = $element['id'];
            $osmType = $element['type'] ?? 'node';
            // "out center" : les node gardent lat/lon direct, les way/relation ont un
            // sous-objet "center" (même valeur numérique de id que d'éventuels node —
            // espaces d'identifiants distincts dans OSM, d'où le repli sur "center").
            $lat = $element['lat'] ?? $element['center']['lat'] ?? null;
            $lon = $element['lon'] ?? $element['center']['lon'] ?? null;
            $nom = $tags['name'] ?? $tags['name:fr'] ?? null;

            if (!$nom || $lat === null || $lon === null) {
                $incomplete++;
                continue;
            }

            // Format historique conservé pour les node (dédoublonnage stable avec les
            // mosquées déjà importées avant l'ajout du support way/relation) ; préfixé par
            // le type pour way/relation, jamais importés avant, pour éviter toute collision
            // avec un id de node numériquement identique.
            $email = $osmType === 'node'
                ? sprintf('mosquee-%d@a-verifier.muslinks.fr', $osmId)
                : sprintf('mosquee-%s-%d@a-verifier.muslinks.fr', $osmType, $osmId);

            if ($this->repo->findOneBy(['email' => $email])) {
                $skipped++;
                continue;
            }

            if ($this->isNearExisting((float) $lat, (float) $lon, $nom, $acceptedLocations)) {
                $tropProche++;
                continue;
            }
            $acceptedLocations[] = [(float) $lat, (float) $lon, $nom];

            $rue = $tags['addr:housenumber'] ?? null;
            $rue = $rue !== null ? trim($rue . ' ' . ($tags['addr:street'] ?? '')) : ($tags['addr:street'] ?? null);
            $cp = $tags['addr:postcode'] ?? null;
            $ville = $tags['addr:city'] ?? null;

            if (!$rue || !$cp || !$ville) {
                $geo = $this->reverseGeocode((float) $lat, (float) $lon);
                $rue = $rue ?? $geo['rue'] ?? null;
                $cp = $cp ?? $geo['cp'] ?? null;
                $ville = $ville ?? $geo['ville'] ?? null;
                usleep(1_100_000); // Nominatim : 1 requête/seconde maximum
            }

            $pro = new Professional();
            $pro->setType(Professional::TYPE_PHYSIQUE);
            $pro->setNomSociete($nom);
            $pro->setDomaineActivite('Mosquée & Religion');
            $pro->setProfession('Mosquée');
            $pro->setSiret(null); // association cultuelle, pas de SIRET commercial
            $pro->setAdresseRue($rue);
            $pro->setCodePostal($cp);
            $pro->setVille($ville ?? 'France');
            $pro->setLatitude((float) $lat);
            $pro->setLongitude((float) $lon);
            $pro->setTelephone($tags['phone'] ?? $tags['contact:phone'] ?? null);
            $pro->setEmail($email);
            $pro->setDescription('Lieu de culte référencé depuis OpenStreetMap.');
            $pro->setSiretValide(false);
            $pro->setStatut(Professional::STATUT_ACTIF);
            $pro->setIsVisible(true);
            $pro->setEmailVerifiedAt(new \DateTimeImmutable());

            $this->em->persist($pro);
            $created++;
            $batch++;

            if ($batch >= 20) {
                $this->em->flush();
                $this->em->clear();
                $batch = 0;
                $io->writeln(sprintf('... %d/%d traitées', $i + 1, $total));
            }
        }

        if ($batch > 0) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%d mosquées créées, %d doublons ignorés, %d écartées (même lieu déjà retenu), %d ignorées (nom ou position manquants).',
            $created,
            $skipped,
            $tropProche,
            $incomplete
        ));

        return Command::SUCCESS;
    }

    /** @param list<array{0: float, 1: float, 2: string}> $acceptedLocations */
    private function isNearExisting(float $lat, float $lon, string $nom, array $acceptedLocations): bool
    {
        $normalizedNom = $this->normalizeName($nom);

        foreach ($acceptedLocations as [$existingLat, $existingLon, $existingNom]) {
            $sameName = $this->normalizeName($existingNom) === $normalizedNom;
            $threshold = $sameName ? 150.0 : 50.0;

            if ($this->distanceMeters($lat, $lon, $existingLat, $existingLon) < $threshold) {
                return true;
            }
        }

        return false;
    }

    private function normalizeName(string $nom): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $nom)));
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function reverseGeocode(float $lat, float $lon): array
    {
        try {
            $response = $this->httpClient->request('GET', self::NOMINATIM_URL, [
                'query'   => ['lat' => $lat, 'lon' => $lon, 'format' => 'json', 'accept-language' => 'fr'],
                'headers' => ['User-Agent' => 'MusLinks/1.0'],
                'timeout' => 8,
            ]);
            $addr = $response->toArray(false)['address'] ?? [];

            return [
                'rue'   => trim(($addr['house_number'] ?? '') . ' ' . ($addr['road'] ?? '')) ?: null,
                'cp'    => $addr['postcode'] ?? null,
                'ville' => $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? null,
            ];
        } catch (\Throwable) {
            return [];
        }
    }
}
