<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tableau de bord « actions à traiter » (EF-28, RG-43).
 * Les compteurs sont alimentés au fil des modules ; les rubriques déjà
 * prévues affichent zéro tant que le module correspondant n'est pas livré.
 */
class TableauDeBordController extends Controller
{
    public function index(Request $request): View
    {
        $compte = $request->user();

        return view('tableau-de-bord.index', [
            'compte' => $compte,
            'profil' => $compte->profil(),
        ]);
    }
}
