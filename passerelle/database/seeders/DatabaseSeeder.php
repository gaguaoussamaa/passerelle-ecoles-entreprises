<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoSeeder::class, CriteresSeeder::class, EcoleSeeder::class, OffresSeeder::class,
            CandidaturesSeeder::class, DeclarationsSeeder::class, MissionsSeeder::class,
            SuiviSeeder::class, EvaluationsSeeder::class,
        ]);
    }
}
