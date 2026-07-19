<?php

use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\Ecole\DiffusionController;
use App\Http\Controllers\Ecole\EtudiantController;
use App\Http\Controllers\Ecole\PartenaireController;
use App\Http\Controllers\Entreprise\OffreController as EntrepriseOffreController;
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
    });

    // ------------------------------------------------- Espace entreprise
    Route::prefix('entreprise')->middleware('role:entreprise')->name('entreprise.')->group(function () {
        Route::get('/offres', [EntrepriseOffreController::class, 'index'])->name('offres');
        Route::post('/offres', [EntrepriseOffreController::class, 'creer'])->name('offres.creer');
        Route::post('/offres/{id}/retirer', [EntrepriseOffreController::class, 'retirer'])->name('offres.retirer');
        Route::post('/offres/{id}/cloturer', [EntrepriseOffreController::class, 'cloturer'])->name('offres.cloturer');
    });

    // --------------------------------------------------- Espace étudiant
    Route::prefix('offres')->middleware('role:etudiant')->name('etudiant.')->group(function () {
        Route::get('/', [EtudiantOffreController::class, 'index'])->name('offres');
        Route::get('/{id}', [EtudiantOffreController::class, 'detail'])->name('offres.detail');
    });
});
