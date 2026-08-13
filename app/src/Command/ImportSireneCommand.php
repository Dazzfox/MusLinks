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
 * Importe de vraies fiches (bouchers, avocats, médecins généralistes) depuis
 * le registre officiel des entreprises françaises (recherche-entreprises.api.gouv.fr) —
 * la même source open data que SiretVerifier. Aucune donnée inventée : SIRET, adresse
 * et coordonnées viennent du registre réel.
 */
#[AsCommand(
    name: 'app:import:sirene',
    description: "Importe bouchers/avocats/médecins réels depuis le registre officiel des entreprises",
)]
class ImportSireneCommand extends Command
{
    private const API_URL = 'https://recherche-entreprises.api.gouv.fr/search';

    private const VILLES = [
        'Paris'         => '75001',
        'Marseille'     => '13001',
        'Lyon'          => '69001',
        'Toulouse'      => '31000',
        'Nice'          => '06000',
        'Nantes'        => '44000',
        'Strasbourg'    => '67000',
        'Montpellier'   => '34000',
        'Bordeaux'      => '33000',
        'Lille'         => '59000',
        'Rennes'        => '35000',
        'Reims'         => '51100',
        'Saint-Étienne' => '42000',
        'Toulon'        => '83000',
        'Grenoble'      => '38000',
    ];

    /** code NAF/APE => [domaine affiché, profession affichée, mention halal à ajouter ?] */
    private const ACTIVITES = [
        '47.22Z' => ['Alimentation', 'Boucher', true],
        '69.10Z' => ['Droit & Conseil', 'Avocat', false],
        '86.21Z' => ['Santé', 'Médecin généraliste', false],
    ];

    private const PAR_VILLE = 3;

    // Un boucher n'est jamais garanti halal (aucune donnée ouverte ne le certifie, d'où la
    // mention de transparence déjà ajoutée à la description) — mais un commerce dont le nom
    // affiche explicitement le porc, ou se revendique d'une autre pratique alimentaire
    // religieuse (casher), n'a lui-même pas sa place mis en avant sur un annuaire pensé pour
    // la communauté musulmane. Cas réels rencontrés : "Cul de Cochon", "Salaison Torrilhon"
    // (charcuterie, quasi systématiquement à base de porc en France même sans le mot "porc"
    // littéral), "L'Écuyer Tranchant Judaïque" (boucherie casher) — aucun n'était écarté par
    // le filtre initial, plus restreint.
    private const MOTS_EXCLUS = [
        'cochon', 'porc', 'porcin', 'jambon', 'lard', 'saucisson', 'charcuterie', 'salaison',
        'judaique', 'judaïque', 'casher', 'kasher', 'kosher',
    ];

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
        $created = 0;
        $skipped = 0;
        $errors = 0;
        $exclus = 0;
        $batch = 0;
        // Un même SIRET peut apparaître dans plusieurs résultats avant le prochain flush() —
        // la vérification en base seule ne voit pas encore ce qui est persist() mais pas flush().
        $seenSirets = [];
        $seenEmails = [];

        foreach (self::ACTIVITES as $code => [$domaine, $profession, $halalNote]) {
            foreach (self::VILLES as $ville => $codePostal) {
                $io->writeln(sprintf('%s à %s...', $profession, $ville));

                try {
                    $response = $this->httpClient->request('GET', self::API_URL, [
                        'query' => [
                            'activite_principale' => $code,
                            'code_postal'         => $codePostal,
                            'etat_administratif'  => 'A',
                            'per_page'            => 10,
                        ],
                        'timeout' => 10,
                    ]);
                    $data = $response->toArray(false);
                } catch (\Throwable $e) {
                    $io->warning(sprintf('Erreur API (%s / %s) : %s', $ville, $code, $e->getMessage()));
                    $errors++;
                    continue;
                }

                $added = 0;
                foreach ($data['results'] ?? [] as $entreprise) {
                    if ($added >= self::PAR_VILLE) {
                        break;
                    }

                    $siege = $entreprise['siege'] ?? [];
                    $siret = $siege['siret'] ?? null;
                    $nom = $entreprise['nom_complet'] ?? null;
                    $adresse = $siege['adresse'] ?? null;
                    $cp = $siege['code_postal'] ?? null;
                    $commune = $siege['libelle_commune'] ?? null;

                    if (!$siret || !$nom || !$adresse || !$cp || !$commune) {
                        continue; // fiche incomplète côté registre, on ne devine rien
                    }

                    if ($halalNote && $this->nomHorsSujetHalal($nom)) {
                        $exclus++;
                        continue;
                    }

                    $email = sprintf('%s@a-verifier.muslinks.fr', $siret);

                    if (isset($seenSirets[$siret]) || isset($seenEmails[$email])) {
                        $skipped++;
                        continue;
                    }

                    if ($this->repo->findOneBy(['siret' => $siret]) || $this->repo->findOneBy(['email' => $email])) {
                        $skipped++;
                        continue;
                    }

                    $seenSirets[$siret] = true;
                    $seenEmails[$email] = true;

                    $description = sprintf('%s référencé depuis le registre officiel des entreprises (SIRET vérifié).', $profession);
                    if ($halalNote) {
                        $description .= ' Statut halal non vérifié par MusLinks — à confirmer directement auprès de l\'établissement.';
                    }

                    $pro = new Professional();
                    $pro->setType(Professional::TYPE_PHYSIQUE);
                    $pro->setNomSociete($nom);
                    $pro->setDomaineActivite($domaine);
                    $pro->setProfession($profession);
                    $pro->setSiret($siret);
                    $pro->setAdresseRue($adresse);
                    $pro->setCodePostal($cp);
                    $pro->setVille($commune);
                    $pro->setLatitude(isset($siege['latitude']) ? (float) $siege['latitude'] : null);
                    $pro->setLongitude(isset($siege['longitude']) ? (float) $siege['longitude'] : null);
                    $pro->setEmail($email);
                    $pro->setDescription($description);
                    $pro->setSiretValide(true);
                    $pro->setStatut(Professional::STATUT_ACTIF);
                    $pro->setIsVisible(true);
                    $pro->setEmailVerifiedAt(new \DateTimeImmutable());

                    $this->em->persist($pro);
                    $created++;
                    $added++;
                    $batch++;

                    if ($batch >= 20) {
                        $this->em->flush();
                        $batch = 0;
                    }
                }

                usleep(300_000); // courtoisie envers l'API publique
            }
        }

        if ($batch > 0) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%d fiches créées, %d doublons ignorés, %d écartées (nom évoquant le porc ou une autre pratique religieuse), %d erreurs API.',
            $created,
            $skipped,
            $exclus,
            $errors
        ));

        return Command::SUCCESS;
    }

    private function nomHorsSujetHalal(string $nom): bool
    {
        $normalized = mb_strtolower($nom);

        foreach (self::MOTS_EXCLUS as $mot) {
            if (str_contains($normalized, $mot)) {
                return true;
            }
        }

        return false;
    }
}
