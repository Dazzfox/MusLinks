<?php

namespace App\Service;

class SiretVerifier
{
    private const API_URL = 'https://entreprise.data.gouv.fr/api/sirene/v3/etablissements/';

    /**
     * Vérifie un numéro SIRET.
     *
     * Retourne :
     *   ['valid' => true,  'data' => [...]]   → SIRET valide, entreprise active
     *   ['valid' => false, 'reason' => '...'] → invalide (format, inconnu, radiée)
     *   ['valid' => null,  'reason' => 'api_down'] → API indisponible, fallback manuel
     */
    public function verify(string $siret): array
    {
        $siret = preg_replace('/[\s\.]/', '', $siret);

        if (!preg_match('/^\d{14}$/', $siret)) {
            return ['valid' => false, 'reason' => 'format'];
        }

        if (!$this->luhn($siret)) {
            return ['valid' => false, 'reason' => 'luhn'];
        }

        $context = stream_context_create([
            'http' => [
                'header'        => "User-Agent: MusLinks/1.0\r\nAccept: application/json\r\n",
                'timeout'       => 6,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents(self::API_URL . $siret, false, $context);

        if ($response === false) {
            return ['valid' => null, 'reason' => 'api_down'];
        }

        $http_code = 0;
        foreach ($http_response_header as $header) {
            if (preg_match('#^HTTP/\S+ (\d{3})#', $header, $m)) {
                $http_code = (int) $m[1];
            }
        }

        if ($http_code === 404) {
            return ['valid' => false, 'reason' => 'not_found'];
        }

        if ($http_code !== 200) {
            return ['valid' => null, 'reason' => 'api_down'];
        }

        $body = json_decode($response, true);
        $etab = $body['etablissement'] ?? null;

        if (!$etab) {
            return ['valid' => false, 'reason' => 'not_found'];
        }

        if (($etab['etat_administratif'] ?? '') !== 'A') {
            return ['valid' => false, 'reason' => 'closed'];
        }

        $ul      = $etab['unite_legale'] ?? [];
        $adresse = $etab['adresse'] ?? [];

        $denomination = $ul['denomination'] ?? null;
        if (!$denomination) {
            $denomination = trim(($ul['prenom_usuel'] ?? '') . ' ' . ($ul['nom'] ?? ''));
        }

        return [
            'valid'      => true,
            'nom'        => $denomination,
            'adresse'    => $adresse['libelle_voie'] ?? '',
            'codePostal' => $adresse['code_postal'] ?? '',
            'ville'      => $adresse['libelle_commune'] ?? '',
        ];
    }

    private function luhn(string $number): bool
    {
        $sum = 0;
        $len = strlen($number);
        for ($i = 0; $i < $len; $i++) {
            $digit = (int) $number[$len - 1 - $i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }
        return $sum % 10 === 0;
    }
}
