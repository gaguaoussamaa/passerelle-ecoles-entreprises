<?php

namespace Database\Seeders;

use App\Models\Compte;
use App\Models\Declaration;
use App\Models\Etudiant;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\TuteurEntreprise;
use App\Models\VersionConvention;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Mission TERMINÉE (« en évaluation », calculé) pour la démo UC-17/TV-26 :
 * Paul Simon (BTS Compta INL) chez TechNova, convention approuvée archivée,
 * rapports rendus SAUF le dernier (consigné à la clôture). En live : TechNova
 * remplit la grille, le responsable tente la clôture avant/après (RG-41/42).
 */
class EvaluationsSeeder extends Seeder
{
    public function run(): void
    {
        $paul = Etudiant::whereHas('compte', fn ($q) => $q->where('email', 'paul.simon@etu-inl.demo'))->firstOrFail();
        $technova = Compte::where('email', 'contact@technova.demo')->firstOrFail();
        $bernard = Compte::where('email', 'e.bernard@inl.demo')->firstOrFail();
        $respInl = Compte::where('email', 'responsable.inl@passerelle.demo')->firstOrFail();

        $debut = now()->subMonths(6)->startOfWeek();
        $fin = now()->subWeeks(2);                                          // terminée → « en évaluation »

        $declaration = Declaration::create([
            'etudiant_id' => $paul->compte_id, 'type_mission' => 'stage', 'statut' => 'recevable',
            'date_debut_prevue' => $debut, 'date_fin_prevue' => $fin,
            'entreprise_saisie' => 'TechNova', 'siret_saisi' => $technova->entreprise->siret,
            'contact_nom' => 'Direction TechNova', 'contact_email' => 'contact@technova.demo',
            'description' => 'Stage en contrôle de gestion : participation aux clôtures mensuelles, fiabilisation des tableaux de bord achats.',
        ]);
        $tuteur = TuteurEntreprise::firstOrCreate(
            ['entreprise_id' => $technova->id, 'nom' => 'Blanchet', 'prenom' => 'Hugo'],
            ['email' => 'h.blanchet@technova.demo'],
        );
        $mission = Mission::create([
            'etudiant_id' => $paul->compte_id, 'entreprise_id' => $technova->id,
            'declaration_id' => $declaration->id, 'tuteur_pedagogique_id' => $bernard->id,
            'tuteur_entreprise_id' => $tuteur->id, 'type' => 'stage',
            'date_debut' => $debut, 'date_fin' => $fin, 'statut' => 'contractualisee',
        ]);

        $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF";
        Storage::put($chemin = 'conventions/'.$mission->id.'/v1.pdf', $pdf);
        $version = VersionConvention::create([
            'mission_id' => $mission->id, 'numero' => 1,
            'fichier_pdf' => $chemin, 'empreinte' => hash('sha256', $pdf), 'statut' => 'approuvee',
        ]);
        foreach (['validation', 'approbation'] as $type) {
            foreach ([[$paul->compte_id, 'etudiant'], [$technova->id, 'entreprise'],
                [$bernard->id, 'tuteur_pedagogique'], [$respInl->id, 'responsable']] as [$compteId, $role]) {
                $version->actions()->create(['compte_id' => $compteId, 'type' => $type, 'role_partie' => $role]);
            }
        }

        // échéancier passé : tout est rendu SAUF le dernier rapport mensuel (TV-26)
        $echeance = $debut->copy()->addMonthNoOverflow();
        $jalons = [];
        while ($echeance->lte($fin)) {
            $jalons[] = Jalon::create(['mission_id' => $mission->id, 'type' => 'rapport_mensuel', 'date_echeance' => $echeance]);
            $echeance = $echeance->copy()->addMonthNoOverflow();
        }
        $jalons[] = Jalon::create([
            'mission_id' => $mission->id, 'type' => 'mi_parcours',
            'date_echeance' => $debut->copy()->addDays((int) ($debut->diffInDays($fin) / 2)),
        ]);
        $dernier = collect($jalons)->sortBy('date_echeance')->last();
        foreach ($jalons as $i => $jalon) {
            if ($jalon->is($dernier)) {
                continue;                                                   // non rendu → consigné à la clôture
            }
            Storage::put($f = 'rapports/'.$mission->id.'/jalon-'.$jalon->id.'.pdf',
                "%PDF-1.4\n% rapport de demonstration\ntrailer << >>\n%%EOF");
            $jalon->update(['fichier_depose' => $f, 'date_depot' => $jalon->date_echeance->copy()->subDay()->setTime(16, 0)]);
        }
    }
}
