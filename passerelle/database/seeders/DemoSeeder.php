<?php

namespace Database\Seeders;

use App\Models\Compte;
use App\Models\Domaine;
use App\Models\Entreprise;
use App\Models\Etablissement;
use App\Models\Responsable;
use Illuminate\Database\Seeder;

/**
 * Jeu de démonstration (EF-30) — incrément « comptes et invitations ».
 * Deux établissements fictifs (preuve du cloisonnement), un compte par rôle
 * disponible à ce stade ; étudiants, tuteurs et dossiers arriveront avec les
 * modules correspondants. Mot de passe commun de démonstration : Passerelle2026!
 */
class DemoSeeder extends Seeder
{
    public const MOT_DE_PASSE = 'Passerelle2026!';

    public function run(): void
    {
        // ----------------------------------------- Référentiel des domaines
        $domaines = [
            'INFO' => 'Informatique et numérique',
            'COMPTA' => 'Comptabilité et gestion',
            'COM' => 'Commerce et vente',
            'MKT' => 'Marketing et communication',
            'RH' => 'Ressources humaines',
            'LOG' => 'Logistique et transport',
            'BTP' => 'Bâtiment et travaux publics',
            'SANTE' => 'Santé et social',
            'INDUS' => 'Industrie et maintenance',
            'TOUR' => 'Hôtellerie, restauration, tourisme',
        ];
        foreach ($domaines as $code => $libelle) {
            Domaine::firstOrCreate(['code' => $code], ['libelle' => $libelle]);
        }

        // ------------------------------------------------- Établissements
        $inl = Etablissement::create([
            'nom' => 'Institut Numérique de Lille',
            'siret' => '11111111100011',
            'ville' => 'Lille',
            'plan_abonnement' => 'standard',
            'debut_abonnement' => '2025-09-01',
            'fin_abonnement' => '2027-08-31',
        ]);

        $horizon = Etablissement::create([
            'nom' => 'Campus Horizon Bordeaux',
            'siret' => '22222222200022',
            'ville' => 'Bordeaux',
            'plan_abonnement' => 'essentiel',
            'debut_abonnement' => '2026-01-01',
            'fin_abonnement' => '2026-12-31',
        ]);

        // --------------------------------------------------------- Comptes
        Compte::create([
            'email' => 'admin@passerelle.demo',
            'mot_de_passe' => self::MOT_DE_PASSE,
            'role' => 'super_admin',
        ]);

        $this->responsable($inl, 'Martin', 'Julien', 'responsable.inl@passerelle.demo');
        $this->responsable($horizon, 'Roux', 'Claire', 'responsable.horizon@passerelle.demo');

        $this->entreprise('TechNova', '33333333300033', 'Lille', 'contact@technova.demo');
        $this->entreprise('Studio Kumo', '44444444400044', 'Roubaix', 'contact@studiokumo.demo');
    }

    private function responsable(Etablissement $etablissement, string $nom, string $prenom, string $email): void
    {
        $compte = Compte::create([
            'email' => $email,
            'mot_de_passe' => self::MOT_DE_PASSE,
            'role' => 'responsable',
        ]);

        Responsable::create([
            'compte_id' => $compte->id,
            'etablissement_id' => $etablissement->id,
            'nom' => $nom,
            'prenom' => $prenom,
        ]);
    }

    private function entreprise(string $raisonSociale, string $siret, string $ville, string $email): void
    {
        $compte = Compte::create([
            'email' => $email,
            'mot_de_passe' => self::MOT_DE_PASSE,
            'role' => 'entreprise',
        ]);

        Entreprise::create([
            'compte_id' => $compte->id,
            'raison_sociale' => $raisonSociale,
            'siret' => $siret,
            'ville' => $ville,
        ]);
    }
}
