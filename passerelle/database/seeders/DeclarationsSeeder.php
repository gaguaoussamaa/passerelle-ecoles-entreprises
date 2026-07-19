<?php

namespace Database\Seeders;

use App\Models\Declaration;
use App\Models\Etudiant;
use Illuminate\Database\Seeder;

/**
 * Déclaration hors plateforme de démonstration : Emma (Campus Horizon) a trouvé
 * un stage chez une entreprise inconnue de la plateforme — le responsable Horizon
 * la valide en direct (TV-15 : compte invité + invitation Mailpit + mission).
 */
class DeclarationsSeeder extends Seeder
{
    public function run(): void
    {
        $emma = Etudiant::whereHas('compte', fn ($q) => $q->where('email', 'emma.petit@etu-horizon.demo'))->firstOrFail();

        Declaration::create([
            'etudiant_id' => $emma->compte_id,
            'type_mission' => 'stage',
            'date_debut_prevue' => '2026-09-07', 'date_fin_prevue' => '2027-01-29',
            'entreprise_saisie' => 'Atelier Brindille', 'siret_saisi' => '88811122200013',
            'contact_nom' => 'Claire Fontaine', 'contact_email' => 'contact@brindille.demo',
            'description' => "Stage de développement d'un site de réservation d'ateliers créatifs (PHP, base de données, intégration), encadré par la gérante.",
        ]);
    }
}
