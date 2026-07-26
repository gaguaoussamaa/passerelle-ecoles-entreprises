<?php

namespace Tests\Feature;

use App\Models\VersionConvention;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Non-régression : les fichiers PDF produits par les seeders de démonstration
 * (conventions, rapports de suivi, CV) doivent être RÉELLEMENT ouvrables —
 * pas des stubs dégénérés. Anomalie corrigée le 26/07/2026 : SuiviSeeder et
 * EvaluationsSeeder écrivaient un placeholder « %PDF-1.4 … %%EOF » sans table
 * de références (illisible dans un lecteur PDF). Les seeders passent désormais
 * par la vraie génération dompdf ; ce test garde le contrat.
 */
class DemoPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_pdf_de_demonstration_sont_reellement_ouvrables(): void
    {
        Storage::fake('local');

        $this->seed(DatabaseSeeder::class);

        $conventions = Storage::allFiles('conventions');
        $rapports = Storage::allFiles('rapports');
        $cv = Storage::allFiles('cv');

        // la démo doit produire des conventions et des rapports (sinon la vérif est vide)
        $this->assertNotEmpty($conventions, 'aucune convention de démonstration produite');
        $this->assertNotEmpty($rapports, 'aucun rapport de démonstration produit');

        foreach (array_merge($conventions, $rapports, $cv) as $fichier) {
            $this->assertPdfOuvrable(Storage::get($fichier), $fichier);
        }

        // empreinte enregistrée == SHA-256 des octets réellement stockés (RG-32)
        foreach (VersionConvention::all() as $version) {
            $this->assertSame(
                hash('sha256', Storage::get($version->fichier_pdf)),
                $version->empreinte,
                "empreinte incohérente pour {$version->fichier_pdf}",
            );
        }
    }

    /**
     * Un PDF ouvrable a une signature, une table de références (startxref) et
     * une fin de fichier. Le stub dégénéré (« %PDF-1.4 … %%EOF » sans startxref)
     * échouait précisément sur l'absence de table de références.
     */
    private function assertPdfOuvrable(string $octets, string $chemin): void
    {
        $this->assertStringStartsWith('%PDF', $octets, "signature PDF absente : $chemin");
        $this->assertStringContainsString('startxref', $octets, "table de références absente : $chemin");
        $this->assertStringContainsString('%%EOF', rtrim($octets), "fin de fichier absente : $chemin");
    }
}
