<?php

namespace App\Services;

use App\Models\Compte;
use App\Models\Jalon;
use App\Models\JournalAudit;
use App\Models\Mission;
use App\Models\VersionConvention;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cycle de vie de la convention (UC-10..13) : génération immuable (RG-31/32/45),
 * circuit séquentiel (RG-33/34), approbations libres (RG-35), contractualisation
 * et échéancier (RG-36/38), annulation-remplacement (RG-37).
 */
class ConventionService
{
    /** RG-31 : liste des données obligatoires manquantes (vide = génération possible). */
    public function manques(Mission $mission): array
    {
        $manques = [];
        if (! $mission->entreprise->siret) {
            $manques[] = 'SIRET de l\'entreprise';
        }
        if (! $mission->entreprise->ville) {
            $manques[] = 'ville de l\'entreprise';
        }
        if (! $mission->tuteur_pedagogique_id) {
            $manques[] = 'tuteur pédagogique';
        }
        if (! $mission->tuteur_entreprise_id) {
            $manques[] = 'tuteur en entreprise';
        }

        return $manques;
    }

    /** Rôle de partie du compte connecté sur cette mission, sinon null (cloisonnement). */
    public function partieDe(Mission $mission, Compte $compte): ?string
    {
        return match (true) {
            $compte->id === $mission->etudiant_id => 'etudiant',
            $compte->id === $mission->entreprise_id => 'entreprise',
            $compte->id === $mission->tuteur_pedagogique_id => 'tuteur_pedagogique',
            $compte->role === 'responsable'
                && $compte->responsable->etablissement_id
                    === $mission->etudiant->promotion->formation->etablissement_id => 'responsable',
            default => null,
        };
    }

    /**
     * RG-32/45 : génère la version n+1 (PDF selon le modèle du type de mission,
     * empreinte SHA-256) ; la version refusée ou annulée devient « remplacée ».
     */
    public function generer(Mission $mission, Compte $auteur): VersionConvention
    {
        return DB::transaction(function () use ($mission, $auteur) {
            $mission->versionsConvention()
                ->whereIn('statut', ['refusee_correction'])
                ->update(['statut' => 'remplacee']);                        // RG-32 / TV-20

            $numero = ($mission->versionsConvention()->max('numero') ?? 0) + 1;
            $modele = $mission->type === 'stage' ? 'pdf.convention-stage' : 'pdf.dossier-alternance';
            $pdf = Pdf::loadView($modele, ['mission' => $mission, 'numero' => $numero])->output();

            $chemin = 'conventions/'.$mission->id.'/v'.$numero.'.pdf';
            Storage::put($chemin, $pdf);

            $version = VersionConvention::create([
                'mission_id' => $mission->id, 'numero' => $numero,
                'fichier_pdf' => $chemin, 'empreinte' => hash('sha256', $pdf),
            ]);
            JournalAudit::tracer('convention_generee', 'version_convention', $version->id, $auteur->id,
                'v'.$numero.' — '.$version->empreinte);

            return $version;
        });
    }

    /** RG-33 : validation par la partie dont c'est le tour, dans l'ordre imposé. */
    public function valider(VersionConvention $version, Compte $compte, string $role): void
    {
        DB::transaction(function () use ($version, $compte, $role) {
            $version->actions()->create(['compte_id' => $compte->id, 'type' => 'validation', 'role_partie' => $role]);
            $version->update(['statut' => $version->prochainValideur() === null ? 'validee' : 'en_validation']);
            JournalAudit::tracer('convention_validee_'.$role, 'version_convention', $version->id, $compte->id);
        });
    }

    /** RG-34 : refus motivé → « refusée (à corriger) » ; la correction produira la version n+1. */
    public function refuser(VersionConvention $version, Compte $compte, string $role, string $motif): void
    {
        DB::transaction(function () use ($version, $compte, $role, $motif) {
            $version->actions()->create(['compte_id' => $compte->id, 'type' => 'refus', 'role_partie' => $role, 'motif' => $motif]);
            $version->update(['statut' => 'refusee_correction']);
            JournalAudit::tracer('convention_refusee_'.$role, 'version_convention', $version->id, $compte->id, $motif);
        });
    }

    /**
     * RG-35/36/38 : approbation en ordre libre (auteur, horodatage et empreinte
     * consignés) ; à la quatrième, mission « contractualisée » + échéancier.
     */
    public function approuver(VersionConvention $version, Compte $compte, string $role): void
    {
        DB::transaction(function () use ($version, $compte, $role) {
            $version->actions()->create(['compte_id' => $compte->id, 'type' => 'approbation', 'role_partie' => $role]);
            JournalAudit::tracer('convention_approuvee_'.$role, 'version_convention', $version->id, $compte->id,
                'empreinte '.$version->empreinte);                          // RG-35

            if ($version->approbationsEffectuees()->count() === count(VersionConvention::ORDRE_VALIDATION)) {
                $version->update(['statut' => 'approuvee']);
                $version->mission->update(['statut' => 'contractualisee']); // RG-36
                $this->genererEcheancier($version->mission);                // RG-38
                JournalAudit::tracer('mission_contractualisee', 'mission', $version->mission_id, $compte->id);
            } else {
                $version->update(['statut' => 'en_approbation']);
            }
        });
    }

    /** RG-37 : une version approuvée ne peut être qu'annulée (motif), puis remplacée. */
    public function annuler(VersionConvention $version, Compte $responsable, string $motif): void
    {
        DB::transaction(function () use ($version, $responsable, $motif) {
            $version->actions()->create(['compte_id' => $responsable->id, 'type' => 'annulation', 'role_partie' => 'responsable', 'motif' => $motif]);
            $version->update(['statut' => 'annulee']);
            $version->mission->update(['statut' => 'en_contractualisation']);
            $version->mission->jalons()->whereNull('fichier_depose')->delete();  // échéancier arrêté
            JournalAudit::tracer('convention_annulee', 'version_convention', $version->id, $responsable->id, $motif);
        });
    }

    /** RG-38 : rapport mensuel à compter de la date de début ; mi-parcours si mission ≥ 2 mois. */
    private function genererEcheancier(Mission $mission): void
    {
        $mission->jalons()->whereNull('fichier_depose')->delete();          // régénération idempotente

        $echeance = $mission->date_debut->copy()->addMonthNoOverflow();
        while ($echeance->lte($mission->date_fin)) {
            Jalon::create(['mission_id' => $mission->id, 'type' => 'rapport_mensuel', 'date_echeance' => $echeance]);
            $echeance = $echeance->copy()->addMonthNoOverflow();
        }

        if ($mission->date_debut->diffInMonths($mission->date_fin) >= 2) {
            Jalon::create([
                'mission_id' => $mission->id, 'type' => 'mi_parcours',
                'date_echeance' => $mission->date_debut->copy()
                    ->addDays((int) ($mission->date_debut->diffInDays($mission->date_fin) / 2)),
            ]);
        }
    }
}
