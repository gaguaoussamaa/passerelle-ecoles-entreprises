<?php

namespace Database\Seeders;

use App\Models\Compte;
use App\Models\Declaration;
use App\Models\Etudiant;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Signalement;
use App\Models\TuteurEntreprise;
use App\Models\VersionConvention;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Mission ACTIVE de démonstration pour le suivi (TV-23/24) : Linh (INL) chez
 * Studio Kumo, convention approuvée archivée, échéancier vivant — 2 rapports
 * rendus (dont 1 tardif), 1 jalon en retard, la suite à venir — et un
 * signalement ouvert à traiter en live par M. Lopez ou le responsable.
 */
class SuiviSeeder extends Seeder
{
    public function run(): void
    {
        $linh = Etudiant::whereHas('compte', fn ($q) => $q->where('email', 'linh.nguyen@etu-inl.demo'))->firstOrFail();
        $kumo = Compte::where('email', 'contact@studiokumo.demo')->firstOrFail();
        $lopez = Compte::where('email', 'm.lopez@inl.demo')->firstOrFail();
        $respInl = Compte::where('email', 'responsable.inl@passerelle.demo')->firstOrFail();

        $debut = now()->subMonths(4)->startOfWeek();                        // mission active depuis 4 mois
        $fin = now()->addMonths(4);

        $declaration = Declaration::create([
            'etudiant_id' => $linh->compte_id, 'type_mission' => 'alternance', 'statut' => 'recevable',
            'date_debut_prevue' => $debut, 'date_fin_prevue' => $fin,
            'entreprise_saisie' => 'Studio Kumo', 'siret_saisi' => $kumo->entreprise->siret,
            'contact_nom' => 'Studio Kumo', 'contact_email' => 'contact@studiokumo.demo',
            'description' => "Alternance : intégration web et maintenance des sites clients de l'agence, sous la responsabilité du gérant.",
        ]);
        $tuteurKumo = TuteurEntreprise::firstOrCreate(
            ['entreprise_id' => $kumo->id, 'nom' => 'Okada', 'prenom' => 'Ren'],
            ['email' => 'r.okada@studiokumo.demo'],
        );
        $mission = Mission::create([
            'etudiant_id' => $linh->compte_id, 'entreprise_id' => $kumo->id,
            'declaration_id' => $declaration->id, 'tuteur_pedagogique_id' => $lopez->id,
            'tuteur_entreprise_id' => $tuteurKumo->id, 'type' => 'alternance',
            'date_debut' => $debut, 'date_fin' => $fin, 'statut' => 'contractualisee',
        ]);

        // convention v1 approuvée : PDF archivé + historique complet des 8 actions
        $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF";
        $chemin = 'conventions/'.$mission->id.'/v1.pdf';
        Storage::put($chemin, $pdf);
        $version = VersionConvention::create([
            'mission_id' => $mission->id, 'numero' => 1,
            'fichier_pdf' => $chemin, 'empreinte' => hash('sha256', $pdf), 'statut' => 'approuvee',
        ]);
        foreach (['validation', 'approbation'] as $type) {
            foreach ([[$linh->compte_id, 'etudiant'], [$kumo->id, 'entreprise'],
                [$lopez->id, 'tuteur_pedagogique'], [$respInl->id, 'responsable']] as [$compteId, $role]) {
                $version->actions()->create(['compte_id' => $compteId, 'type' => $type, 'role_partie' => $role]);
            }
        }

        // échéancier ancré sur les dates (RG-38) : rendu, rendu tardif, EN RETARD, à venir
        $rapport = static fn (int $n): string => "%PDF-1.4\n% rapport de demonstration $n\ntrailer << >>\n%%EOF";
        $echeance = $debut->copy()->addMonthNoOverflow();
        $numero = 0;
        while ($echeance->lte($fin)) {
            $jalon = Jalon::create(['mission_id' => $mission->id, 'type' => 'rapport_mensuel', 'date_echeance' => $echeance]);
            $numero++;
            if ($numero === 1) {                                            // rendu à l'heure
                Storage::put($f = 'rapports/'.$mission->id.'/jalon-'.$jalon->id.'.pdf', $rapport(1));
                $jalon->update(['fichier_depose' => $f, 'date_depot' => $jalon->date_echeance->copy()->subDay()->setTime(14, 0)]);
            } elseif ($numero === 2) {                                      // rendu tardif (historique visible)
                Storage::put($f = 'rapports/'.$mission->id.'/jalon-'.$jalon->id.'.pdf', $rapport(2));
                $jalon->update(['fichier_depose' => $f, 'date_depot' => $jalon->date_echeance->copy()->addDays(6)->setTime(9, 30)]);
            }                                                               // n°3 échu sans dépôt → EN RETARD (RG-39)
            $echeance = $echeance->copy()->addMonthNoOverflow();
        }
        Jalon::create([
            'mission_id' => $mission->id, 'type' => 'mi_parcours',
            'date_echeance' => $debut->copy()->addDays((int) ($debut->diffInDays($fin) / 2)),
        ]);

        // signalement ouvert (RG-40) — à prendre en charge et clore en démonstration
        Signalement::create([
            'mission_id' => $mission->id, 'emetteur_id' => $linh->compte_id,
            'description' => "Depuis trois semaines, les tâches confiées ne correspondent plus à la mission prévue (uniquement du support téléphonique) et mon tuteur en entreprise n'est plus disponible.",
        ]);
    }
}
