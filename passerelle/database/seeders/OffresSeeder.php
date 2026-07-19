<?php

namespace Database\Seeders;

use App\Models\Compte;
use App\Models\Diffusion;
use App\Models\Etablissement;
use App\Models\Domaine;
use App\Models\Offre;
use App\Models\Partenariat;
use App\Models\Promotion;
use Illuminate\Database\Seeder;

/** Partenariats et offres de démonstration : chaque état de diffusion représenté. */
class OffresSeeder extends Seeder
{
    public function run(): void
    {
        $inl = Etablissement::where('nom', 'Institut Numérique de Lille')->firstOrFail();
        $horizon = Etablissement::where('nom', 'Campus Horizon Bordeaux')->firstOrFail();
        $technova = Compte::where('email', 'contact@technova.demo')->firstOrFail();
        $kumo = Compte::where('email', 'contact@studiokumo.demo')->firstOrFail();

        // TechNova : partenaire des DEUX écoles (transversalité) ; Kumo : INL seulement
        foreach ([[$inl, $technova], [$horizon, $technova], [$inl, $kumo]] as [$etab, $ent]) {
            Partenariat::firstOrCreate(
                ['etablissement_id' => $etab->id, 'entreprise_id' => $ent->id],
                ['statut' => 'actif'],
            );
        }

        $info = Domaine::where('code', 'INFO')->firstOrFail();
        $compta = Domaine::where('code', 'COMPTA')->firstOrFail();

        // Offre multi-écoles : validée+affectée à l'INL, encore soumise à Horizon
        $fullstack = Offre::create([
            'entreprise_id' => $technova->id, 'domaine_id' => $info->id,
            'intitule' => 'Alternant développeur full-stack',
            'description' => "Développement d'applications web internes (PHP/JS), participation aux revues de code et aux mises en production, montée en compétence encadrée par un tuteur senior.",
            'type' => 'alternance', 'niveau' => 'Bac+5', 'lieu' => 'Lille',
            'date_debut_prevue' => '2026-09-21', 'date_fin_prevue' => '2027-09-17', 'nb_postes' => 2,
        ]);
        $dInl = Diffusion::create(['offre_id' => $fullstack->id, 'etablissement_id' => $inl->id, 'statut' => 'validee']);
        Diffusion::create(['offre_id' => $fullstack->id, 'etablissement_id' => $horizon->id]);   // soumise
        $fullstack->promotions()->sync([Promotion::where('libelle', 'M2 Dev 2026-2027')->firstOrFail()->id]);

        // Offre soumise à l'INL (à modérer en démo)
        Offre::create([
            'entreprise_id' => $kumo->id, 'domaine_id' => $info->id,
            'intitule' => 'Stage développeur web',
            'description' => "Refonte du site vitrine de l'agence et intégration de maquettes, sous la responsabilité du gérant.",
            'type' => 'stage', 'niveau' => 'Bac+5', 'lieu' => 'Roubaix',
            'date_debut_prevue' => '2026-09-01', 'date_fin_prevue' => '2027-02-26',
        ])->diffusions()->create(['etablissement_id' => $inl->id]);

        // Offre refusée (motif) — pour l'historique de modération
        Offre::create([
            'entreprise_id' => $technova->id, 'domaine_id' => $compta->id,
            'intitule' => 'Stage assistant contrôle de gestion',
            'description' => 'Participation aux clôtures mensuelles et au reporting.',
            'type' => 'stage', 'niveau' => 'Bac+2', 'lieu' => 'Lille',
            'date_debut_prevue' => '2026-10-01', 'date_fin_prevue' => '2026-12-18',
        ])->diffusions()->create([
            'etablissement_id' => $horizon->id, 'statut' => 'refusee',
            'motif_refus' => 'Aucune formation en gestion dans notre établissement.',
        ]);
    }
}
