<?php

namespace Database\Seeders;

use App\Models\Compte;
use App\Models\Declaration;
use App\Models\Etudiant;
use App\Models\Mission;
use App\Models\Partenariat;
use App\Models\TuteurEntreprise;
use Illuminate\Database\Seeder;

/**
 * Mission « en contractualisation » prête pour la démonstration du circuit de
 * convention : Sarah (INL) chez TechNova, tuteurs déjà désignés. Les quatre
 * parties (Sarah, TechNova, M. Lopez, responsable INL) ont un mot de passe.
 */
class MissionsSeeder extends Seeder
{
    public function run(): void
    {
        $sarah = Etudiant::whereHas('compte', fn ($q) => $q->where('email', 'sarah.kaddouri@etu-inl.demo'))->firstOrFail();
        $technova = Compte::where('email', 'contact@technova.demo')->firstOrFail();
        $lopez = Compte::where('email', 'm.lopez@inl.demo')->firstOrFail();

        // mission trouvée par Sarah elle-même chez TechNova (chemin B, recevable)
        $declaration = Declaration::create([
            'etudiant_id' => $sarah->compte_id, 'type_mission' => 'alternance', 'statut' => 'recevable',
            'date_debut_prevue' => '2026-09-14', 'date_fin_prevue' => '2027-07-16',
            'entreprise_saisie' => 'TechNova', 'siret_saisi' => $technova->entreprise->siret,
            'contact_nom' => 'Direction TechNova', 'contact_email' => 'contact@technova.demo',
            'description' => "Alternance au sein de l'équipe outils internes : développement de modules PHP, participation aux revues de code et à l'assistance de niveau 2.",
        ]);

        $tuteurEntreprise = TuteurEntreprise::firstOrCreate(
            ['entreprise_id' => $technova->id, 'nom' => 'Marchand', 'prenom' => 'Julie'],
            ['email' => 'j.marchand@technova.demo'],
        );

        Mission::create([
            'etudiant_id' => $sarah->compte_id,
            'entreprise_id' => $technova->id,
            'declaration_id' => $declaration->id,
            'tuteur_pedagogique_id' => $lopez->id,
            'tuteur_entreprise_id' => $tuteurEntreprise->id,
            'type' => 'alternance',
            'date_debut' => '2026-09-14', 'date_fin' => '2027-07-16',
            'statut' => 'en_contractualisation',
        ]);

        Partenariat::firstOrCreate(
            ['etablissement_id' => $sarah->promotion->formation->etablissement_id, 'entreprise_id' => $technova->id],
            ['statut' => 'actif'],
        );
    }
}
