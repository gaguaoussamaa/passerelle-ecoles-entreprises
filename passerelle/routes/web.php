<?php

use App\Http\Controllers\Admin\EtablissementController as AdminEtablissementController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\ConventionController;
use App\Http\Controllers\JalonController;
use App\Http\Controllers\SignalementController;
use App\Http\Controllers\Etudiant\SuiviController as EtudiantSuiviController;
use App\Http\Controllers\Tuteur\SuiviController as TuteurSuiviController;
use App\Http\Controllers\Etudiant\ConventionController as EtudiantConventionController;
use App\Http\Controllers\Tuteur\ConventionController as TuteurConventionController;
use App\Http\Controllers\Ecole\DeclarationController as EcoleDeclarationController;
use App\Http\Controllers\Ecole\DiffusionController;
use App\Http\Controllers\Ecole\EtudiantController;
use App\Http\Controllers\Ecole\MissionController as EcoleMissionController;
use App\Http\Controllers\Ecole\PartenaireController;
use App\Http\Controllers\Entreprise\MissionController as EntrepriseMissionController;
use App\Http\Controllers\Etudiant\DeclarationController as EtudiantDeclarationController;
use App\Http\Controllers\Entreprise\CandidatureController as EntrepriseCandidatureController;
use App\Http\Controllers\Entreprise\OffreController as EntrepriseOffreController;
use App\Http\Controllers\Etudiant\CandidatureController as EtudiantCandidatureController;
use App\Http\Controllers\Etudiant\OffreController as EtudiantOffreController;
use App\Http\Controllers\Ecole\FormationController;
use App\Http\Controllers\Ecole\PromotionController;
use App\Http\Controllers\Ecole\TuteurController;
use App\Http\Controllers\TableauDeBordController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/connexion');

// ------------------------------------------------------ Accès non authentifié
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'afficher'])->name('connexion');
    Route::post('/connexion', [ConnexionController::class, 'connecter'])->name('connexion.soumettre');
    Route::get('/activation/{jeton}', [ActivationController::class, 'afficher'])->name('activation.afficher');
    Route::post('/activation', [ActivationController::class, 'activer'])->name('activation.soumettre');
});

// -------------------------------------------------------- Espace authentifié
Route::middleware(['auth', 'acces'])->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'deconnecter'])->name('deconnexion');
    Route::get('/tableau-de-bord', [TableauDeBordController::class, 'index'])->name('tableau-de-bord');

    // ------------------------------------------- Espace super-administrateur
    Route::prefix('admin')->middleware('role:super_admin')->name('admin.')->group(function () {
        Route::get('/etablissements', [AdminEtablissementController::class, 'index'])->name('etablissements');
        Route::post('/etablissements', [AdminEtablissementController::class, 'creer'])->name('etablissements.creer');
    });

    // -------------------------- Espace école (responsable d'établissement)
    Route::prefix('ecole')->middleware('role:responsable')->name('ecole.')->group(function () {
        Route::get('/formations', [FormationController::class, 'index'])->name('formations');
        Route::post('/formations', [FormationController::class, 'creer'])->name('formations.creer');
        Route::post('/formations/{id}/archiver', [FormationController::class, 'archiver'])->name('formations.archiver');

        Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions');
        Route::post('/promotions', [PromotionController::class, 'creer'])->name('promotions.creer');
        Route::post('/promotions/{id}/archiver', [PromotionController::class, 'archiver'])->name('promotions.archiver');

        Route::get('/tuteurs', [TuteurController::class, 'index'])->name('tuteurs');
        Route::post('/tuteurs', [TuteurController::class, 'creer'])->name('tuteurs.creer');

        Route::get('/etudiants', [EtudiantController::class, 'index'])->name('etudiants');
        Route::post('/etudiants', [EtudiantController::class, 'creer'])->name('etudiants.creer');
        Route::get('/etudiants/import', [EtudiantController::class, 'importFormulaire'])->name('etudiants.import');
        Route::post('/etudiants/import', [EtudiantController::class, 'importTraiter'])->name('etudiants.import.traiter');
        Route::get('/etudiants/import/modele', [EtudiantController::class, 'importModele'])->name('etudiants.import.modele');
        Route::get('/etudiants/{id}', [EtudiantController::class, 'fiche'])->name('etudiants.fiche');
        Route::post('/etudiants/{id}/statut', [EtudiantController::class, 'changerStatut'])->name('etudiants.statut');
        Route::post('/etudiants/{id}/promotion', [EtudiantController::class, 'changerPromotion'])->name('etudiants.promotion');
        Route::post('/etudiants/{id}/invitation', [EtudiantController::class, 'renvoyerInvitation'])->name('etudiants.invitation');
        Route::delete('/etudiants/{id}', [EtudiantController::class, 'supprimer'])->name('etudiants.supprimer');

        Route::get('/partenaires', [PartenaireController::class, 'index'])->name('partenaires');
        Route::post('/partenaires', [PartenaireController::class, 'inviter'])->name('partenaires.inviter');

        Route::get('/offres', [DiffusionController::class, 'index'])->name('offres');
        Route::post('/offres/{id}/valider', [DiffusionController::class, 'valider'])->name('offres.valider');
        Route::post('/offres/{id}/refuser', [DiffusionController::class, 'refuser'])->name('offres.refuser');

        Route::get('/declarations', [EcoleDeclarationController::class, 'index'])->name('declarations');
        Route::post('/declarations/{id}/valider', [EcoleDeclarationController::class, 'valider'])->name('declarations.valider');
        Route::post('/declarations/{id}/refuser', [EcoleDeclarationController::class, 'refuser'])->name('declarations.refuser');

        Route::get('/missions', [EcoleMissionController::class, 'index'])->name('missions');
        Route::post('/missions/{id}/tuteur', [EcoleMissionController::class, 'designerTuteur'])->name('missions.tuteur');
        Route::post('/missions/{id}/invitation', [EcoleMissionController::class, 'renvoyerInvitation'])->name('missions.invitation');
        Route::post('/missions/{id}/annuler', [EcoleMissionController::class, 'annuler'])->name('missions.annuler');
        Route::post('/missions/{id}/convention', [EcoleMissionController::class, 'genererConvention'])->name('missions.convention');
        Route::post('/missions/{id}/cloturer', [EcoleMissionController::class, 'cloturer'])->name('missions.cloturer');
    });

    // ------------------- Conventions : actions des quatre parties (partie
    // résolue dans le contrôleur ; un tiers obtient 404 — cloisonnement)
    Route::get('/conventions/{id}/pdf', [ConventionController::class, 'pdf'])->name('conventions.pdf');
    Route::post('/conventions/{id}/valider', [ConventionController::class, 'valider'])->name('conventions.valider');
    Route::post('/conventions/{id}/refuser', [ConventionController::class, 'refuser'])->name('conventions.refuser');
    Route::post('/conventions/{id}/approuver', [ConventionController::class, 'approuver'])->name('conventions.approuver');
    Route::post('/conventions/{id}/annuler', [ConventionController::class, 'annuler'])->name('conventions.annuler');

    // -------- Suivi : rapports sur jalons (dépôt étudiant, lecture parties
    // école) et traitement des signalements (tuteur/responsable) — RG-39/40
    Route::post('/jalons/{id}/rapport', [JalonController::class, 'deposer'])->name('jalons.deposer');
    Route::get('/jalons/{id}/rapport', [JalonController::class, 'rapport'])->name('jalons.rapport');
    Route::post('/signalements/{id}/prendre', [SignalementController::class, 'prendre'])->name('signalements.prendre');
    Route::post('/signalements/{id}/clore', [SignalementController::class, 'clore'])->name('signalements.clore');

    // --------------------------------------------- Espace tuteur pédagogique
    Route::prefix('tuteur')->middleware('role:tuteur_pedagogique')->name('tuteur.')->group(function () {
        Route::get('/conventions', [TuteurConventionController::class, 'index'])->name('conventions');
        Route::get('/suivi', [TuteurSuiviController::class, 'index'])->name('suivi');
    });

    // ------------------------------------------------- Espace entreprise
    Route::prefix('entreprise')->middleware('role:entreprise')->name('entreprise.')->group(function () {
        Route::get('/offres', [EntrepriseOffreController::class, 'index'])->name('offres');
        Route::post('/offres', [EntrepriseOffreController::class, 'creer'])->name('offres.creer');
        Route::post('/offres/{id}/retirer', [EntrepriseOffreController::class, 'retirer'])->name('offres.retirer');
        Route::post('/offres/{id}/cloturer', [EntrepriseOffreController::class, 'cloturer'])->name('offres.cloturer');

        Route::get('/candidatures', [EntrepriseCandidatureController::class, 'index'])->name('candidatures');
        Route::post('/candidatures/{id}/statut', [EntrepriseCandidatureController::class, 'changerStatut'])->name('candidatures.statut');
        Route::get('/candidatures/{id}/cv', [EntrepriseCandidatureController::class, 'cv'])->name('candidatures.cv');

        Route::get('/missions', [EntrepriseMissionController::class, 'index'])->name('missions');
        Route::post('/missions/{id}/tuteur', [EntrepriseMissionController::class, 'designerTuteur'])->name('missions.tuteur');
        Route::post('/missions/{id}/signaler', [EntrepriseMissionController::class, 'signaler'])->name('missions.signaler');
        Route::post('/missions/{id}/evaluation', [EntrepriseMissionController::class, 'evaluer'])->name('missions.evaluation');
    });

    // --------------------------------------------------- Espace étudiant
    Route::middleware('role:etudiant')->name('etudiant.')->group(function () {
        Route::prefix('offres')->group(function () {
            Route::get('/', [EtudiantOffreController::class, 'index'])->name('offres');
            Route::get('/{id}', [EtudiantOffreController::class, 'detail'])->name('offres.detail');
            Route::post('/{id}/candidater', [EtudiantCandidatureController::class, 'candidater'])->name('offres.candidater');
        });
        Route::get('/convention', [EtudiantConventionController::class, 'index'])->name('convention');
        Route::get('/suivi', [EtudiantSuiviController::class, 'index'])->name('suivi');
        Route::post('/suivi/missions/{id}/signaler', [EtudiantSuiviController::class, 'signaler'])->name('suivi.signaler');
        Route::prefix('declaration')->group(function () {
            Route::get('/', [EtudiantDeclarationController::class, 'index'])->name('declaration');
            Route::post('/', [EtudiantDeclarationController::class, 'soumettre'])->name('declaration.soumettre');
            Route::post('/abandonner', [EtudiantDeclarationController::class, 'abandonner'])->name('declaration.abandonner');
        });
        Route::prefix('candidatures')->group(function () {
            Route::get('/', [EtudiantCandidatureController::class, 'index'])->name('candidatures');
            Route::post('/cv', [EtudiantCandidatureController::class, 'deposerCv'])->name('candidatures.cv.deposer');
            Route::get('/cv', [EtudiantCandidatureController::class, 'telechargerCv'])->name('candidatures.cv');
            Route::get('/{id}/cv', [EtudiantCandidatureController::class, 'cvCandidature'])->name('candidatures.cv.depose');
            Route::post('/{id}/retirer', [EtudiantCandidatureController::class, 'retirer'])->name('candidatures.retirer');
            Route::post('/{id}/confirmer', [EtudiantCandidatureController::class, 'confirmer'])->name('candidatures.confirmer');
            Route::post('/{id}/decliner', [EtudiantCandidatureController::class, 'decliner'])->name('candidatures.decliner');
        });
    });
});
