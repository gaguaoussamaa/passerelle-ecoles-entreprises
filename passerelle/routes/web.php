<?php

use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\ConnexionController;
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
});
