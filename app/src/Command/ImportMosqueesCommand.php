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

        $query = <<<OVERPASS
            [out:json][timeout:90];
            area["ISO3166-1"="FR"][admin_level=2]->.fr;
            node["amenity"="place_of_worship"]["religion"="muslim"](area.fr);
            out body;
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
        $io->writeln(sprintf('%d mosquées trouvées sur OpenStreetMap.', $total));

        $created = 0;
        $skipped = 0;
        $incomplete = 0;
        $batch = 0;

        foreach ($elements as $i => $element) {
            $tags = $element['tags'] ?? [];
            $osmId = $element['id'];
            $lat = $element['lat'] ?? null;
            $lon = $element['lon'] ?? null;
            $nom = $tags['name'] ?? $tags['name:fr'] ?? null;

            if (!$nom || $lat === null || $lon === null) {
                $incomplete++;
                continue;
            }

            $email = sprintf('mosquee-%d@a-verifier.muslinks.fr', $osmId);
            if ($this->repo->findOneBy(['email' => $email])) {
                $skipped++;
                continue;
            }

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
            '%d mosquées créées, %d doublons ignorés, %d ignorées (nom ou position manquants).',
            $created,
            $skipped,
            $incomplete
        ));

        return Command::SUCCESS;
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
