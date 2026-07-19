<?php

namespace Database\Seeders;

use App\Models\Compte;
use App\Models\Domaine;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\TuteurPedagogique;
use Illuminate\Database\Seeder;

/**
 * Jeu de démonstration — structure école (EF-30).
 * INL : 3 formations multi-domaines (preuve du ciblage), 4 promotions dont une
 * archivée, tuteurs et étudiants à tous les statuts significatifs.
 * Horizon : structure minimale, contre-témoin du cloisonnement.
 */
class EcoleSeeder extends Seeder
{
    public function run(): void
    {
        $inl = Etablissement::where('nom', 'Institut Numérique de Lille')->firstOrFail();
        $horizon = Etablissement::where('nom', 'Campus Horizon Bordeaux')->firstOrFail();
        $dom = fn (string $code) => Domaine::where('code', $code)->firstOrFail()->id;

        // ------------------------------------------------------------- INL
        $m2dev = $this->formation($inl, 'M2 Développement informatique', 'Bac+5', 'alternance', [$dom('INFO')]);
        $btsCompta = $this->formation($inl, 'BTS Comptabilité et gestion', 'Bac+2', 'stage', [$dom('COMPTA')]);
        $bachMkt = $this->formation($inl, 'Bachelor Marketing digital', 'Bac+3', 'les_deux', [$dom('MKT'), $dom('COM')]);

        $pM2 = Promotion::create(['formation_id' => $m2dev->id, 'libelle' => 'M2 Dev 2026-2027', 'annee_universitaire' => '2026-2027']);
        Promotion::create(['formation_id' => $m2dev->id, 'libelle' => 'M2 Dev 2025-2026', 'annee_universitaire' => '2025-2026', 'archivee' => true]);
        $pBts = Promotion::create(['formation_id' => $btsCompta->id, 'libelle' => 'BTS Compta 2026-2027', 'annee_universitaire' => '2026-2027']);
        $pMkt = Promotion::create(['formation_id' => $bachMkt->id, 'libelle' => 'Bachelor MKT 2026-2027', 'annee_universitaire' => '2026-2027']);

        $this->tuteur($inl, 'Lopez', 'Marc', 'm.lopez@inl.demo', [$m2dev->id], actif: true);
        $this->tuteur($inl, 'Bernard', 'Émilie', 'e.bernard@inl.demo', [$btsCompta->id, $bachMkt->id], actif: true);

        // étudiants M2 Dev — dont les protagonistes des scénarios de démonstration
        $this->etudiant($pM2, 'Kaddouri', 'Sarah', 'sarah.kaddouri@etu-inl.demo', 'actif', avecMdp: true);
        $this->etudiant($pM2, 'Benali', 'Mehdi', 'mehdi.benali@etu-inl.demo', 'actif', avecMdp: true);
        $this->etudiant($pM2, 'Moreau', 'Léa', 'lea.moreau@etu-inl.demo', 'invite');
        $this->etudiant($pM2, 'Garcia', 'Thomas', 'thomas.garcia@etu-inl.demo', 'sorti');
        $this->etudiant($pM2, 'Nguyen', 'Linh', 'linh.nguyen@etu-inl.demo', 'actif', avecMdp: true);  // protagoniste du suivi
        // BTS Compta et Bachelor MKT — preuve du ciblage par formation
        $this->etudiant($pBts, 'Simon', 'Paul', 'paul.simon@etu-inl.demo', 'actif');
        $this->etudiant($pBts, 'Dubois', 'Camille', 'camille.dubois@etu-inl.demo', 'actif');
        $this->etudiant($pMkt, 'Rossi', 'Chiara', 'chiara.rossi@etu-inl.demo', 'actif');

        // --------------------------------------------------------- Horizon
        $btsCom = $this->formation($horizon, 'BTS Commerce', 'Bac+2', 'stage', [$dom('COM')]);
        $pCom = Promotion::create(['formation_id' => $btsCom->id, 'libelle' => 'BTS Commerce 2026-2027', 'annee_universitaire' => '2026-2027']);

        $this->tuteur($horizon, 'Faure', 'Antoine', 'a.faure@horizon.demo', [$btsCom->id], actif: true);
        $this->etudiant($pCom, 'Petit', 'Emma', 'emma.petit@etu-horizon.demo', 'actif', avecMdp: true);
        $this->etudiant($pCom, 'Marchand', 'Lucas', 'lucas.marchand@etu-horizon.demo', 'actif');
        $this->etudiant($pCom, 'Diallo', 'Awa', 'awa.diallo@etu-horizon.demo', 'invite');
    }

    private function formation(Etablissement $etab, string $intitule, string $niveau, string $type, array $domaines): Formation
    {
        $formation = Formation::create([
            'etablissement_id' => $etab->id, 'intitule' => $intitule,
            'niveau' => $niveau, 'type_mission' => $type,
        ]);
        $formation->domaines()->sync($domaines);

        return $formation;
    }

    private function tuteur(Etablissement $etab, string $nom, string $prenom, string $email, array $formations, bool $actif = false): void
    {
        $compte = Compte::create([
            'email' => $email,
            'mot_de_passe' => $actif ? DemoSeeder::MOT_DE_PASSE : null,
            'role' => 'tuteur_pedagogique',
        ]);
        $tuteur = TuteurPedagogique::create([
            'compte_id' => $compte->id, 'etablissement_id' => $etab->id,
            'nom' => $nom, 'prenom' => $prenom,
        ]);
        $tuteur->formations()->sync($formations);
    }

    private function etudiant(Promotion $promotion, string $nom, string $prenom, string $email, string $statut, bool $avecMdp = false): void
    {
        $compte = Compte::create([
            'email' => $email,
            'mot_de_passe' => $avecMdp ? DemoSeeder::MOT_DE_PASSE : null,
            'role' => 'etudiant',
        ]);
        Etudiant::create([
            'compte_id' => $compte->id, 'promotion_id' => $promotion->id,
            'nom' => $nom, 'prenom' => $prenom, 'statut_scolarite' => $statut,
        ]);
    }
}
