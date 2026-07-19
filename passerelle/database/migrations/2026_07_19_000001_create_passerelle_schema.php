<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schéma initial de Passerelle — traduction exacte du MPD de référence
 * (livrables/05-Conception-detaillee/mpd.sql, 28 tables).
 * L'alignement est contrôlé par outils/build/verifier-mpd.sh --compare-export.
 * Les évolutions ultérieures du schéma feront l'objet de migrations dédiées.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------- Référentiel
        Schema::create('domaines', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('libelle', 100);
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
        });

        // ----------------------------------------------- Comptes (table mère)
        Schema::create('comptes', function (Blueprint $t) {
            $t->id();
            $t->string('email', 190)->unique();                      // RG-03
            $t->string('mot_de_passe', 255)->nullable();             // haché ; NULL avant activation (RG-04)
            $t->string('role', 25);
            $t->boolean('actif')->default(true);                     // désactivation des rôles non étudiants
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
        });

        Schema::create('invitations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('compte_id');
            $t->char('jeton_hash', 64)->unique();                    // RG-01
            $t->dateTime('expire_le');
            $t->dateTime('utilisee_le')->nullable();
            $t->string('statut', 15)->default('active');             // RG-02
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->foreign('compte_id')->references('id')->on('comptes')->restrictOnDelete();
        });

        // --------------------------------------------------- Établissements
        Schema::create('etablissements', function (Blueprint $t) {
            $t->id();
            $t->string('nom', 150);
            $t->string('siret', 14);
            $t->string('ville', 100);
            $t->string('logo', 255)->nullable();
            $t->string('plan_abonnement', 30);                       // RG-44
            $t->date('debut_abonnement');
            $t->date('fin_abonnement');
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
        });

        // ------------------------------------- Profils (tables filles 1:1)
        Schema::create('responsables', function (Blueprint $t) {
            $t->unsignedBigInteger('compte_id')->primary();          // PK = FK : héritage 1:1
            $t->unsignedBigInteger('etablissement_id');
            $t->string('nom', 80);
            $t->string('prenom', 80);
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->foreign('compte_id')->references('id')->on('comptes')->restrictOnDelete();
            $t->foreign('etablissement_id')->references('id')->on('etablissements')->restrictOnDelete();
        });

        Schema::create('tuteurs_pedagogiques', function (Blueprint $t) {
            $t->unsignedBigInteger('compte_id')->primary();
            $t->unsignedBigInteger('etablissement_id');
            $t->string('nom', 80);
            $t->string('prenom', 80);
            $t->boolean('actif')->default(true);                     // RG-12
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->foreign('compte_id')->references('id')->on('comptes')->restrictOnDelete();
            $t->foreign('etablissement_id')->references('id')->on('etablissements')->restrictOnDelete();
        });

        Schema::create('entreprises', function (Blueprint $t) {
            $t->unsignedBigInteger('compte_id')->primary();
            $t->string('raison_sociale', 150);
            $t->string('siret', 14)->nullable();                     // format seul (hors API INSEE)
            $t->string('ville', 100)->nullable();
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->foreign('compte_id')->references('id')->on('comptes')->restrictOnDelete();
        });

        // ---------------------------------------------- Structure école
        Schema::create('formations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('etablissement_id');
            $t->string('intitule', 150);
            $t->string('niveau', 20);
            $t->string('type_mission', 15);
            $t->boolean('archivee')->default(false);                 // RG-10
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->foreign('etablissement_id')->references('id')->on('etablissements')->restrictOnDelete();
        });

        Schema::create('formation_domaine', function (Blueprint $t) {
            $t->unsignedBigInteger('formation_id');
            $t->unsignedBigInteger('domaine_id');
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->primary(['formation_id', 'domaine_id']);
            $t->foreign('formation_id')->references('id')->on('formations')->restrictOnDelete();
            $t->foreign('domaine_id')->references('id')->on('domaines')->restrictOnDelete();
        });

        Schema::create('promotions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('formation_id');
            $t->string('libelle', 100);
            $t->string('annee_universitaire', 9);
            $t->boolean('archivee')->default(false);                 // RG-11
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->foreign('formation_id')->references('id')->on('formations')->restrictOnDelete();
        });

        Schema::create('etudiants', function (Blueprint $t) {
            $t->unsignedBigInteger('compte_id')->primary();
            $t->unsignedBigInteger('promotion_id');                  // RG-14
            $t->string('nom', 80);
            $t->string('prenom', 80);
            $t->string('statut_scolarite', 15)->default('invite');   // RG-05
            $t->string('cv_profil', 255)->nullable();
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->foreign('compte_id')->references('id')->on('comptes')->restrictOnDelete();
            $t->foreign('promotion_id')->references('id')->on('promotions')->restrictOnDelete();
        });

        Schema::create('rattachements', function (Blueprint $t) {
            $t->unsignedBigInteger('tuteur_id');
            $t->unsignedBigInteger('formation_id');
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->primary(['tuteur_id', 'formation_id']);
            $t->foreign('tuteur_id')->references('compte_id')->on('tuteurs_pedagogiques')->restrictOnDelete();
            $t->foreign('formation_id')->references('id')->on('formations')->restrictOnDelete();
        });

        Schema::create('tuteurs_entreprise', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('entreprise_id');
            $t->string('nom', 80);
            $t->string('prenom', 80);
            $t->string('email', 190)->nullable();                    // pas de compte (Should)
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->foreign('entreprise_id')->references('compte_id')->on('entreprises')->restrictOnDelete();
        });

        Schema::create('partenariats', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('etablissement_id');
            $t->unsignedBigInteger('entreprise_id');
            $t->string('statut', 15)->default('en_attente');         // RG-47
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->unique(['etablissement_id', 'entreprise_id'], 'uq_partenariat');
            $t->foreign('etablissement_id')->references('id')->on('etablissements')->restrictOnDelete();
            $t->foreign('entreprise_id')->references('compte_id')->on('entreprises')->restrictOnDelete();
        });

        // --------------------------------------------- Offres et diffusions
        Schema::create('offres', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('entreprise_id');
            $t->unsignedBigInteger('domaine_id');
            $t->string('intitule', 150);
            $t->text('description');
            $t->string('type', 15);
            $t->string('niveau', 20);
            $t->string('lieu', 100);
            $t->date('date_debut_prevue');
            $t->date('date_fin_prevue');
            $t->unsignedSmallInteger('nb_postes')->default(1);
            $t->string('statut', 15)->default('publiee');            // RG-21
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->foreign('entreprise_id')->references('compte_id')->on('entreprises')->restrictOnDelete();
            $t->foreign('domaine_id')->references('id')->on('domaines')->restrictOnDelete();
        });

        Schema::create('diffusions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('offre_id');
            $t->unsignedBigInteger('etablissement_id');
            $t->string('statut', 15)->default('soumise');            // RG-17
            $t->text('motif_refus')->nullable();                     // RG-18
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->unique(['offre_id', 'etablissement_id'], 'uq_diffusion');
            $t->foreign('offre_id')->references('id')->on('offres')->restrictOnDelete();
            $t->foreign('etablissement_id')->references('id')->on('etablissements')->restrictOnDelete();
        });

        Schema::create('affectations', function (Blueprint $t) {
            $t->unsignedBigInteger('offre_id');
            $t->unsignedBigInteger('promotion_id');
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->primary(['offre_id', 'promotion_id']);
            $t->foreign('offre_id')->references('id')->on('offres')->restrictOnDelete();
            $t->foreign('promotion_id')->references('id')->on('promotions')->restrictOnDelete();
        });

        // ------------------------------------ Candidatures et déclarations
        Schema::create('candidatures', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('etudiant_id');
            $t->unsignedBigInteger('offre_id');
            $t->string('statut', 20)->default('recue');              // RG-23
            $t->text('message')->nullable();
            $t->string('cv_depose', 255);                            // RG-48 : copie du CV
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->unique(['etudiant_id', 'offre_id'], 'uq_candidature'); // RG-22
            $t->foreign('etudiant_id')->references('compte_id')->on('etudiants')->restrictOnDelete();
            $t->foreign('offre_id')->references('id')->on('offres')->restrictOnDelete();
        });

        Schema::create('declarations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('etudiant_id');
            $t->string('type_mission', 15);
            $t->date('date_debut_prevue');
            $t->date('date_fin_prevue');
            $t->string('entreprise_saisie', 150);
            $t->string('siret_saisi', 14)->nullable();
            $t->string('contact_nom', 120);
            $t->string('contact_email', 190);
            $t->text('description');
            $t->string('statut', 15)->default('soumise');            // RG-46
            $t->text('motif_refus')->nullable();
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->foreign('etudiant_id')->references('compte_id')->on('etudiants')->restrictOnDelete();
        });

        // ------------------------------------------------------- Missions
        Schema::create('missions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('etudiant_id');
            $t->unsignedBigInteger('entreprise_id');
            $t->unsignedBigInteger('candidature_id')->nullable()->unique();  // origine chemin A
            $t->unsignedBigInteger('declaration_id')->nullable()->unique();  // origine chemin B
            $t->unsignedBigInteger('tuteur_pedagogique_id')->nullable();     // RG-28
            $t->unsignedBigInteger('tuteur_entreprise_id')->nullable();
            $t->string('type', 15);
            $t->date('date_debut');
            $t->date('date_fin');
            $t->string('statut', 25)->default('en_montage');         // états temporels dérivés (RG-29)
            $t->text('motif_arret')->nullable();                     // RG-30
            $t->date('date_effet_arret')->nullable();
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->foreign('etudiant_id')->references('compte_id')->on('etudiants')->restrictOnDelete();
            $t->foreign('entreprise_id')->references('compte_id')->on('entreprises')->restrictOnDelete();
            $t->foreign('candidature_id')->references('id')->on('candidatures')->restrictOnDelete();
            $t->foreign('declaration_id')->references('id')->on('declarations')->restrictOnDelete();
            $t->foreign('tuteur_pedagogique_id')->references('compte_id')->on('tuteurs_pedagogiques')->restrictOnDelete();
            $t->foreign('tuteur_entreprise_id')->references('id')->on('tuteurs_entreprise')->restrictOnDelete();
        });

        // ---------------------------------------------------- Conventions
        Schema::create('versions_convention', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('mission_id');
            $t->unsignedSmallInteger('numero');
            $t->string('fichier_pdf', 255);
            $t->char('empreinte', 64);                               // SHA-256 (RG-32)
            $t->string('statut', 25)->default('emise');
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->unique(['mission_id', 'numero'], 'uq_version');
            $t->foreign('mission_id')->references('id')->on('missions')->restrictOnDelete();
        });

        Schema::create('actions_convention', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('version_id');
            $t->unsignedBigInteger('compte_id');                     // auteur réel
            $t->string('type', 15);
            $t->string('role_partie', 25);                           // RG-33
            $t->text('motif')->nullable();
            $t->dateTime('created_at');                              // horodatage de l'action
            $t->dateTime('updated_at');
            $t->foreign('version_id')->references('id')->on('versions_convention')->restrictOnDelete();
            $t->foreign('compte_id')->references('id')->on('comptes')->restrictOnDelete();
        });

        // ------------------------------------------------ Suivi et clôture
        Schema::create('jalons', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('mission_id');
            $t->string('type', 20);
            $t->date('date_echeance');                               // RG-38
            $t->string('fichier_depose', 255)->nullable();           // « rendu » / « en retard » dérivés (RG-39)
            $t->dateTime('date_depot')->nullable();
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->foreign('mission_id')->references('id')->on('missions')->restrictOnDelete();
        });

        Schema::create('signalements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('mission_id');
            $t->unsignedBigInteger('emetteur_id');
            $t->unsignedBigInteger('traitant_id')->nullable();
            $t->text('description');
            $t->string('statut', 15)->default('ouvert');             // RG-40
            $t->text('issue')->nullable();
            $t->dateTime('created_at');                              // date du signalement
            $t->dateTime('updated_at');
            $t->foreign('mission_id')->references('id')->on('missions')->restrictOnDelete();
            $t->foreign('emetteur_id')->references('id')->on('comptes')->restrictOnDelete();
            $t->foreign('traitant_id')->references('id')->on('comptes')->restrictOnDelete();
        });

        Schema::create('criteres', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('libelle', 150);
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
        });

        Schema::create('evaluations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('mission_id')->unique();          // RG-41
            $t->unsignedBigInteger('tuteur_entreprise_id');          // auteur métier
            $t->text('commentaire')->nullable();
            $t->dateTime('created_at');                              // date de l'évaluation
            $t->dateTime('updated_at');
            $t->foreign('mission_id')->references('id')->on('missions')->restrictOnDelete();
            $t->foreign('tuteur_entreprise_id')->references('id')->on('tuteurs_entreprise')->restrictOnDelete();
        });

        Schema::create('notes_criteres', function (Blueprint $t) {
            $t->unsignedBigInteger('evaluation_id');
            $t->unsignedBigInteger('critere_id');
            $t->unsignedTinyInteger('note');
            $t->dateTime('created_at');
            $t->dateTime('updated_at');
            $t->primary(['evaluation_id', 'critere_id']);
            $t->foreign('evaluation_id')->references('id')->on('evaluations')->restrictOnDelete();
            $t->foreign('critere_id')->references('id')->on('criteres')->restrictOnDelete();
        });

        // ------------------------------------------ Traçabilité technique
        Schema::create('journal_audit', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('compte_id')->nullable();         // NULL = action système
            $t->string('action', 60);
            $t->string('objet_type', 40);
            $t->unsignedBigInteger('objet_id');
            $t->text('details')->nullable();
            $t->dateTime('created_at');                              // horodatage
            $t->dateTime('updated_at');
            $t->index(['objet_type', 'objet_id'], 'idx_jaud_objet');
            $t->foreign('compte_id')->references('id')->on('comptes')->restrictOnDelete();
        });

        // ---------------- Contraintes CHECK (MySQL >= 8.0.16), noms du MPD ----------------
        $checks = [
            "ALTER TABLE comptes ADD CONSTRAINT chk_comptes_role CHECK (role IN ('etudiant','tuteur_pedagogique','responsable','entreprise','super_admin'))",
            "ALTER TABLE invitations ADD CONSTRAINT chk_invitations_statut CHECK (statut IN ('active','utilisee','invalidee'))",
            "ALTER TABLE etablissements ADD CONSTRAINT chk_etab_abonnement CHECK (fin_abonnement >= debut_abonnement)",
            "ALTER TABLE formations ADD CONSTRAINT chk_form_type CHECK (type_mission IN ('stage','alternance','les_deux'))",
            "ALTER TABLE etudiants ADD CONSTRAINT chk_etu_statut CHECK (statut_scolarite IN ('invite','actif','diplome','sorti'))",
            "ALTER TABLE partenariats ADD CONSTRAINT chk_part_statut CHECK (statut IN ('en_attente','actif'))",
            "ALTER TABLE offres ADD CONSTRAINT chk_offre_type CHECK (type IN ('stage','alternance'))",
            "ALTER TABLE offres ADD CONSTRAINT chk_offre_statut CHECK (statut IN ('publiee','pourvue','retiree'))",
            "ALTER TABLE offres ADD CONSTRAINT chk_offre_dates CHECK (date_fin_prevue >= date_debut_prevue)",
            "ALTER TABLE diffusions ADD CONSTRAINT chk_diff_statut CHECK (statut IN ('soumise','validee','refusee','caduque'))",
            "ALTER TABLE candidatures ADD CONSTRAINT chk_cand_statut CHECK (statut IN ('recue','preselectionnee','entretien','retenue','confirmee','declinee','refusee','retiree'))",
            "ALTER TABLE declarations ADD CONSTRAINT chk_decl_type CHECK (type_mission IN ('stage','alternance'))",
            "ALTER TABLE declarations ADD CONSTRAINT chk_decl_statut CHECK (statut IN ('soumise','recevable','refusee','abandonnee'))",
            "ALTER TABLE declarations ADD CONSTRAINT chk_decl_dates CHECK (date_fin_prevue >= date_debut_prevue)",
            "ALTER TABLE missions ADD CONSTRAINT chk_mis_type CHECK (type IN ('stage','alternance'))",
            "ALTER TABLE missions ADD CONSTRAINT chk_mis_statut CHECK (statut IN ('en_montage','en_contractualisation','contractualisee','cloturee','annulee','interrompue'))",
            "ALTER TABLE missions ADD CONSTRAINT chk_mis_origine CHECK ((candidature_id IS NULL) <> (declaration_id IS NULL))",
            "ALTER TABLE missions ADD CONSTRAINT chk_mis_dates CHECK (date_fin >= date_debut)",
            "ALTER TABLE versions_convention ADD CONSTRAINT chk_ver_statut CHECK (statut IN ('emise','en_validation','validee','en_approbation','approuvee','refusee_correction','remplacee','annulee'))",
            "ALTER TABLE actions_convention ADD CONSTRAINT chk_act_type CHECK (type IN ('validation','refus','approbation','annulation'))",
            "ALTER TABLE actions_convention ADD CONSTRAINT chk_act_role CHECK (role_partie IN ('etudiant','entreprise','tuteur_pedagogique','responsable'))",
            "ALTER TABLE jalons ADD CONSTRAINT chk_jal_type CHECK (type IN ('rapport_mensuel','mi_parcours'))",
            "ALTER TABLE signalements ADD CONSTRAINT chk_sig_statut CHECK (statut IN ('ouvert','en_cours','clos'))",
            "ALTER TABLE notes_criteres ADD CONSTRAINT chk_nc_note CHECK (note <= 5)",
        ];
        foreach ($checks as $sql) {
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        foreach (['journal_audit','notes_criteres','evaluations','criteres','signalements','jalons',
                  'actions_convention','versions_convention','missions','declarations','candidatures',
                  'affectations','diffusions','offres','partenariats','tuteurs_entreprise','rattachements',
                  'etudiants','promotions','formation_domaine','formations','entreprises',
                  'tuteurs_pedagogiques','responsables','etablissements','invitations','comptes','domaines'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
