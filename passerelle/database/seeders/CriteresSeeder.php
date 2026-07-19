<?php

namespace Database\Seeders;

use App\Models\Critere;
use Illuminate\Database\Seeder;

/** Grille d'évaluation standard de la plateforme (RG-41) — référentiel commun. */
class CriteresSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['integration', 'Intégration dans l\'équipe et savoir-être'],
            ['competences', 'Maîtrise des compétences techniques attendues'],
            ['autonomie', 'Autonomie et prise d\'initiative'],
            ['communication', 'Communication écrite et orale'],
            ['fiabilite', 'Assiduité et fiabilité dans les livrables'],
        ] as [$code, $libelle]) {
            Critere::firstOrCreate(['code' => $code], ['libelle' => $libelle]);
        }
    }
}
