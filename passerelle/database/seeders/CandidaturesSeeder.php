<?php

namespace Database\Seeders;

use App\Models\Candidature;
use App\Models\Etudiant;
use App\Models\Offre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Candidatures de démonstration sur l'offre TechNova (validée à l'INL, promo M2 Dev) :
 * Sarah « reçue » (à instruire en démo), Mehdi « retenue » (confirmation live → mission),
 * Linh « refusée » (historique). CV PDF minimal réellement téléchargeable (RG-48).
 */
class CandidaturesSeeder extends Seeder
{
    public function run(): void
    {
        $offre = Offre::where('intitule', 'Alternant développeur full-stack')->firstOrFail();

        $dossiers = [
            ['sarah.kaddouri@etu-inl.demo', 'recue',
                "Alternante en M2 Développement, je maîtrise PHP/Laravel et JavaScript ; votre stack correspond exactement à mon projet professionnel."],
            ['mehdi.benali@etu-inl.demo', 'retenue',
                "Passionné de développement web, j'ai réalisé deux projets Laravel en équipe et je cherche une alternance exigeante avec revues de code."],
            ['linh.nguyen@etu-inl.demo', 'refusee',
                'Je souhaite rejoindre votre équipe pour progresser en développement full-stack.'],
        ];

        foreach ($dossiers as [$email, $statut, $message]) {
            $etudiant = Etudiant::whereHas('compte', fn ($q) => $q->where('email', $email))->firstOrFail();

            $profil = 'cv/profils/'.$etudiant->compte_id.'.pdf';
            Storage::put($profil, $this->pdf($etudiant->prenom.' '.$etudiant->nom));
            $etudiant->update(['cv_profil' => $profil]);

            $copie = 'cv/candidatures/'.$etudiant->compte_id.'-'.$offre->id.'.pdf';
            Storage::copy($profil, $copie);                                  // RG-48 : copie figée

            Candidature::create([
                'etudiant_id' => $etudiant->compte_id, 'offre_id' => $offre->id,
                'statut' => $statut, 'message' => $message, 'cv_depose' => $copie,
            ]);
        }
    }

    /** PDF une page minimal mais valide — suffisant pour la démonstration du téléchargement. */
    private function pdf(string $nom): string
    {
        $texte = 'CV de demonstration - '.str_replace(['é', 'è'], 'e', $nom);
        $flux = "BT /F1 18 Tf 72 720 Td ($texte) Tj ET";
        $objets = [
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj",
            "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj",
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj",
            '4 0 obj << /Length '.strlen($flux)." >> stream\n".$flux."\nendstream endobj",
            "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj",
        ];

        $pdf = "%PDF-1.4\n";
        $positions = [];
        foreach ($objets as $objet) {
            $positions[] = strlen($pdf);
            $pdf .= $objet."\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objets) + 1)."\n0000000000 65535 f \n";
        foreach ($positions as $position) {
            $pdf .= sprintf("%010d 00000 n \n", $position);
        }

        return $pdf."trailer << /Size ".(count($objets) + 1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
