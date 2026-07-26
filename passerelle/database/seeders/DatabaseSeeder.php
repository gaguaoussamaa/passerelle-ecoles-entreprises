<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Repartir d'un stockage privé propre : les fichiers de démo (conventions,
        // CV, rapports) sont intégralement régénérés par les seeders ci-dessous.
        // Évite les fichiers orphelins d'une exécution précédente (démo reproductible).
        foreach (['conventions', 'cv', 'rapports'] as $dossier) {
            Storage::deleteDirectory($dossier);
        }

        $this->call([
            DemoSeeder::class, CriteresSeeder::class, EcoleSeeder::class, OffresSeeder::class,
            CandidaturesSeeder::class, DeclarationsSeeder::class, MissionsSeeder::class,
            SuiviSeeder::class, EvaluationsSeeder::class,
        ]);
    }
}
