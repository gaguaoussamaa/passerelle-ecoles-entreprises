<?php

namespace App\Services;

use App\Models\Compte;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\JournalAudit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Import des étudiants par fichier CSV (EF-07, RG-13).
 * Format : en-tête `nom;prenom;email;promotion` puis une ligne par étudiant,
 * `promotion` = libellé exact d'une promotion active de l'établissement.
 * Les lignes valides sont créées (compte « invité » + profil + invitation),
 * les invalides rejetées avec motif — rapport ligne à ligne retourné.
 */
class ImportEtudiantsService
{
    public function __construct(private readonly InvitationService $invitations) {}

    /** @return array{crees:int, rejets:int, lignes:list<array{ligne:int,email:string,resultat:string,motif:string}>} */
    public function importer(UploadedFile $fichier, Etablissement $etablissement): array
    {
        $promotions = $etablissement->formations()->where('archivee', false)
            ->with(['promotions' => fn ($q) => $q->where('archivee', false)])
            ->get()->pluck('promotions')->flatten()->keyBy(fn ($p) => mb_strtolower($p->libelle));

        $rapport = ['crees' => 0, 'rejets' => 0, 'lignes' => []];
        $flux = fopen($fichier->getRealPath(), 'r');
        $numero = 0;

        while (($champs = fgetcsv($flux, 1000, ';')) !== false) {
            $numero++;
            if ($numero === 1 && isset($champs[0]) && mb_strtolower(trim($champs[0])) === 'nom') {
                continue; // ligne d'en-tête
            }
            if (count(array_filter($champs, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // ligne vide
            }

            [$nom, $prenom, $email, $libellePromotion] = array_map(
                fn ($i) => trim((string) ($champs[$i] ?? '')), [0, 1, 2, 3]
            );

            $motif = $this->motifDeRejet($nom, $prenom, $email, $libellePromotion, $promotions);

            if ($motif !== null) {
                $rapport['rejets']++;
                $rapport['lignes'][] = ['ligne' => $numero, 'email' => $email ?: '—', 'resultat' => 'Rejeté', 'motif' => $motif];
                continue;
            }

            $compte = DB::transaction(function () use ($nom, $prenom, $email, $libellePromotion, $promotions) {
                $compte = Compte::create(['email' => $email, 'role' => 'etudiant']);
                Etudiant::create([
                    'compte_id' => $compte->id,
                    'promotion_id' => $promotions[mb_strtolower($libellePromotion)]->id,
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'statut_scolarite' => 'invite',
                ]);
                JournalAudit::tracer('etudiant_importe', 'compte', $compte->id);

                return $compte;
            });

            $this->invitations->inviter($compte);

            $rapport['crees']++;
            $rapport['lignes'][] = ['ligne' => $numero, 'email' => $email, 'resultat' => 'Créé (invité)', 'motif' => '—'];
        }
        fclose($flux);

        return $rapport;
    }

    private function motifDeRejet(string $nom, string $prenom, string $email, string $libellePromotion, $promotions): ?string
    {
        if ($nom === '' || $prenom === '' || $email === '' || $libellePromotion === '') {
            return 'Champ obligatoire manquant (attendu : nom;prenom;email;promotion)';
        }
        if (Validator::make(['email' => $email], ['email' => 'email:rfc'])->fails()) {
            return 'Adresse e-mail invalide';
        }
        if (Compte::where('email', $email)->exists()) {
            return 'Adresse e-mail déjà utilisée sur la plateforme';        // RG-03
        }
        if (! isset($promotions[mb_strtolower($libellePromotion)])) {
            return "Promotion « {$libellePromotion} » inconnue ou archivée dans l'établissement";
        }

        return null;
    }
}
